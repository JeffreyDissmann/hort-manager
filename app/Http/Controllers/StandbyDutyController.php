<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\StandbyDuty;
use App\Support\StandbyFill;
use App\Support\StandbyRoster;
use App\Support\StandbySwaps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * „Springerdienst" — the month list of who stands by when Erzieher are ill. Everyone
 * reads it; the coordinator fills it by hand, optionally starting from a fair random
 * proposal for the month's empty slots (`?propose=1`), which is only shown, never saved
 * until the coordinator saves it.
 */
class StandbyDutyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StandbyDuty::class);

        $user = $request->user();
        $month = $this->month($request->query('month'));
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth()->startOfDay();
        $canManage = $user->can('manage', StandbyDuty::class);

        // Only the slots still ahead are proposed; past days are history.
        $proposeFrom = $from->max(Carbon::today());
        $proposal = $canManage && $request->boolean('propose') && $proposeFrom->lte($to)
            ? app(StandbyFill::class)->propose($proposeFrom, $to)
            : null;

        return Inertia::render('Standby/Index', [
            'month' => [
                'value' => $month->format('Y-m'),
                'label' => $month->locale(app()->getLocale())->isoFormat('MMMM YYYY'),
                'prev' => $month->copy()->subMonthNoOverflow()->format('Y-m'),
                'next' => $month->copy()->addMonthNoOverflow()->format('Y-m'),
                'is_current' => $month->isSameMonth(Carbon::today()),
            ],
            'days' => StandbyRoster::forRange($from, $to, $user),
            'canManage' => $canManage,
            'children' => $canManage ? StandbyRoster::eligibleChildren($from, $to) : [],
            'counts' => $canManage ? $this->schoolYearCounts($from) : [],
            'proposal' => $proposal,
            // Swaps: the viewer's own dates they could offer, and their open requests.
            'ownUpcoming' => StandbyRoster::upcomingFor($user),
            'swaps' => app(StandbySwaps::class)->openFor($user),
            'today' => Carbon::today()->toDateString(),
        ]);
    }

    /**
     * Save any number of slots at once — a single change or a whole (adjusted) proposal.
     * A null child clears the slot.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage', StandbyDuty::class);

        $data = $request->validate([
            'assignments' => ['required', 'array', 'min:1', 'max:100'],
            'assignments.*.date' => ['required', 'date_format:Y-m-d'],
            'assignments.*.slot' => ['required', 'integer', 'min:1', 'max:'.StandbyRoster::SLOTS_CARE],
            'assignments.*.child_id' => ['nullable', 'integer', 'exists:children,id'],
        ]);

        $assignments = collect($data['assignments']);
        $slots = StandbyRoster::slotsBetween($assignments->min('date'), $assignments->max('date'));

        // A slot the calendar doesn't have (a weekend, a Schließzeit, a second slot on a
        // normal day) or a child not enrolled that day can't be filled.
        foreach ($assignments as $i => $assignment) {
            if ($assignment['slot'] > ($slots[$assignment['date']] ?? 0)) {
                throw ValidationException::withMessages(["assignments.{$i}.slot" => __('standby.no_such_slot')]);
            }

            if ($assignment['child_id'] !== null && ! Child::find($assignment['child_id'])->isActiveOn($assignment['date'])) {
                throw ValidationException::withMessages(["assignments.{$i}.child_id" => __('standby.not_enrolled')]);
            }
        }

        DB::transaction(function () use ($assignments, $request): void {
            foreach ($assignments as $assignment) {
                $key = ['date' => $assignment['date'], 'slot' => $assignment['slot']];

                if ($assignment['child_id'] === null) {
                    StandbyDuty::query()->whereDate('date', $assignment['date'])->where('slot', $assignment['slot'])->get()->each->delete();

                    continue;
                }

                $duty = StandbyDuty::query()->whereDate('date', $assignment['date'])->where('slot', $assignment['slot'])->first()
                    ?? new StandbyDuty($key);

                $duty->fill(['child_id' => $assignment['child_id'], 'assigned_by' => $request->user()->id])->save();
            }
        });

        return back()->with('status', trans_choice('flash.standby_saved', $assignments->count(), ['count' => $assignments->count()]));
    }

    /** The requested month (`YYYY-MM`), falling back to the current one. */
    private function month(mixed $value): Carbon
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return Carbon::createFromFormat('!Y-m', $value)->startOfMonth();
        }

        return Carbon::today()->startOfMonth();
    }

    /**
     * Each child's Springerdienste in the school year of the shown month — so the
     * coordinator sees who is due when filling by hand.
     *
     * @return array<int, int>
     */
    private function schoolYearCounts(Carbon $month): array
    {
        [$start, $end] = StandbyDuty::schoolYearOf($month);

        return StandbyDuty::query()
            ->between($start, $end)
            ->whereNotNull('child_id')
            ->selectRaw('child_id, count(*) as aggregate')
            ->groupBy('child_id')
            ->pluck('aggregate', 'child_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }
}
