<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\CategoryDirection;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\Child;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only „Einnahmen je Kind": a child × month matrix of confirmed parent
 * contributions for a chosen year, split by category. The user picks which top-level
 * income group to analyse (usually „Beiträge", pre-selected as the first one) and,
 * optionally, a single stream within it. Contributions not linked to a real child
 * (free text / a user / nothing) are collected into a flagged „Nicht zugeordnet" row
 * that deep-links to those bookings so they can be corrected.
 */
class ContributionController extends Controller
{
    public function index(Request $request): Response
    {
        $roots = Category::query()
            ->whereNull('parent_id')
            ->where('direction', CategoryDirection::Income->value)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name']);

        // The selected income group (defaults to the first, usually „Beiträge").
        $rootId = $roots->contains('id', $request->integer('root'))
            ? $request->integer('root')
            : (int) ($roots->first()->id ?? 0);
        $root = $rootId ? Category::find($rootId) : null;

        // Direct children drive the per-child breakdown (in tree order). Every deeper
        // category is mapped back to its top-level stream so a (grand-)child booking
        // rolls up there — and the scope covers the whole subtree, matching the
        // full-subtree deep-link to the bookings list.
        [$streams, $streamOf, $scopeIds] = $this->scope($root);

        $years = $this->availableYears($scopeIds);
        $year = $request->integer('year') ?: (int) $years->first();

        // Child-attributed contributions → the per-child matrix, split by stream so
        // each child shows which streams they paid (and, as gaps, which they didn't).
        $attributed = $this->contributions($scopeIds)
            ->whereNotNull('counterparty_child_id')
            ->whereYear('booking_date', $year)
            ->get(['counterparty_child_id', 'category_id', 'amount_cents', 'booking_date']);

        $perChild = [];   // [child][month] total
        $perCategory = []; // [child][stream][month]
        $monthTotals = array_fill(1, 12, 0);

        foreach ($attributed as $booking) {
            // Roll the booking's category up to its top-level stream (skip anything
            // booked directly on the group root — it has no specific stream).
            $stream = $streamOf[$booking->category_id] ?? null;
            if ($stream === null) {
                continue;
            }

            $month = $booking->booking_date->month;
            $child = $booking->counterparty_child_id;
            $perChild[$child][$month] = ($perChild[$child][$month] ?? 0) + $booking->amount_cents;
            $perCategory[$child][$stream][$month] = ($perCategory[$child][$stream][$month] ?? 0) + $booking->amount_cents;
            $monthTotals[$month] += $booking->amount_cents;
        }

        $rows = Child::orderBy('name')->get(['id', 'name'])
            ->map(fn (Child $child): array => [
                'id' => $child->id,
                'name' => $child->name,
                'months' => collect(range(1, 12))->map(fn (int $m): int => $perChild[$child->id][$m] ?? 0)->all(),
                'total' => array_sum($perChild[$child->id] ?? []),
                // Every sub-category of the group, in tree order — unpaid ones show as
                // gaps so a missing contribution is always visible.
                'breakdown' => $streams->map(fn (Category $s): array => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'months' => collect(range(1, 12))->map(fn (int $m): int => $perCategory[$child->id][$s->id][$m] ?? 0)->all(),
                    'total' => array_sum($perCategory[$child->id][$s->id] ?? []),
                ])->all(),
            ]);

        // Contributions NOT linked to a real child — these are likely misassigned.
        $unlinked = $this->contributions($scopeIds)
            ->whereNull('counterparty_child_id')
            ->whereYear('booking_date', $year)
            ->get(['amount_cents', 'booking_date']);

        $unassignedMonths = array_fill(1, 12, 0);
        foreach ($unlinked as $booking) {
            $unassignedMonths[$booking->booking_date->month] += $booking->amount_cents;
        }

        $now = CarbonImmutable::now();

        return Inertia::render('Accounting/Contributions/Index', [
            'roots' => $roots,
            'root' => $rootId,
            'year' => $year,
            'years' => $years,
            'monthLabels' => collect(range(1, 12))
                ->map(fn (int $m): string => CarbonImmutable::create($year, $m, 1)->translatedFormat('M')),
            'rows' => $rows,
            'monthTotals' => array_values($monthTotals),
            'grandTotal' => array_sum($monthTotals),
            'unassignedMonths' => array_values($unassignedMonths),
            'unassignedTotal' => array_sum($unassignedMonths),
            // The category to deep-link the „fix these" list to — the whole group.
            'unassignedCategoryId' => $rootId ?: null,
            // Up to which month a missing payment is meaningful (a blank future month isn't).
            'pastMonths' => $year < $now->year ? 12 : ($year === $now->year ? $now->month : 0),
        ]);
    }

    /**
     * Resolve the selected group into: its direct children (the streams, in tree
     * order), a map of every subtree category id → its top-level stream id, and the
     * full set of in-scope category ids (all descendants + the root itself).
     *
     * @return array{0: Collection<int, Category>, 1: array<int, int>, 2: Collection<int, int>}
     */
    private function scope(?Category $root): array
    {
        if (! $root) {
            return [collect(), [], collect()];
        }

        $childrenByParent = Category::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $streams = $root->children()->orderBy('position')->orderBy('name')->get(['id', 'name']);

        $streamOf = [];
        foreach ($streams as $stream) {
            foreach ($this->descendantsInclusive($stream->id, $childrenByParent) as $id) {
                $streamOf[$id] = $stream->id;
            }
        }

        return [$streams, $streamOf, collect(array_keys($streamOf))->push($root->id)];
    }

    /**
     * A category id plus every descendant id beneath it.
     *
     * @param  Collection<int|null, Collection<int, Category>>  $childrenByParent
     * @return list<int>
     */
    private function descendantsInclusive(int $rootId, Collection $childrenByParent): array
    {
        $ids = [];
        $stack = [$rootId];

        while ($stack !== []) {
            $id = array_pop($stack);
            $ids[] = $id;

            foreach ($childrenByParent->get($id, collect()) as $child) {
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * Confirmed income within the selected group's category subtree.
     *
     * @param  Collection<int, int>  $scopeIds
     */
    private function contributions(Collection $scopeIds): Builder
    {
        return Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->where('kind', BookingKind::Income)
            ->whereIn('category_id', $scopeIds);
    }

    /**
     * Every year from the oldest to the newest contribution, newest first (falls
     * back to this year when there is none yet).
     *
     * @param  Collection<int, int>  $scopeIds
     * @return Collection<int, int>
     */
    private function availableYears(Collection $scopeIds): Collection
    {
        $newest = $this->contributions($scopeIds)->max('booking_date');
        $oldest = $this->contributions($scopeIds)->min('booking_date');

        if ($newest === null) {
            return collect([(int) now()->year]);
        }

        return collect(range(CarbonImmutable::parse($newest)->year, CarbonImmutable::parse($oldest)->year));
    }
}
