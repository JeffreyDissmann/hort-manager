<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Enums\CategoryDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreCategoryRequest;
use App\Http\Requests\Accounting\UpdateCategoryRequest;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Admin-only editor for the nested booking-category tree. */
class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->withCount('bookings')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return Inertia::render('Accounting/Categories/Index', [
            'trees' => [
                'income' => $this->tree($categories, CategoryDirection::Income),
                'expense' => $this->tree($categories, CategoryDirection::Expense),
            ],
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $parentId = $request->filled('parent_id') ? $request->integer('parent_id') : null;

        Category::create([
            'name' => $request->string('name')->trim()->value(),
            'parent_id' => $parentId,
            'direction' => $request->resolvedDirection(),
            'position' => $this->nextPosition($parentId),
        ]);

        return back()->with('status', __('flash.category_created'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update([
            'name' => $request->string('name')->trim()->value(),
            'comment' => $request->input('comment') ?: null,
            'active' => $request->boolean('active'),
        ]);

        return back()->with('status', __('flash.category_updated'));
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $moveTo = $request->integer('move_to') ?: null;

        // Deleting cascades to children and nulls the category on any bookings. If the
        // node or a descendant still carries bookings, they must first be moved to
        // another category of the same direction (outside this subtree). The whole
        // thing runs in one transaction with a locking read.
        return DB::transaction(function () use ($category, $moveTo): RedirectResponse {
            $all = Category::get(['id', 'parent_id', 'direction']);
            $subtreeIds = $this->subtree($all, $category->id)->pluck('id');
            $bookings = Booking::whereIn('category_id', $subtreeIds)->lockForUpdate();

            if ($bookings->clone()->exists()) {
                $target = $moveTo ? $all->firstWhere('id', $moveTo) : null;

                // A valid target: exists, not part of this subtree, same direction.
                if (! $target || $subtreeIds->contains($target->id) || $target->direction !== $category->direction) {
                    return back()->with('status', __('flash.category_has_bookings', ['name' => $category->name]));
                }

                $bookings->update(['category_id' => $target->id]);
            }

            $category->delete();

            return back()->with('status', __('flash.category_deleted', ['name' => $category->name]));
        });
    }

    /**
     * Build the nested tree for one direction from a flat, pre-counted collection.
     *
     * @param  Collection<int, Category>  $categories
     * @return list<array<string, mixed>>
     */
    private function tree(Collection $categories, CategoryDirection $direction, ?int $parentId = null): array
    {
        return $categories
            ->where('direction', $direction)
            ->where('parent_id', $parentId)
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'comment' => $category->comment,
                'active' => $category->active,
                'bookings_count' => $category->bookings_count,
                'children' => $this->tree($categories, $direction, $category->id),
            ])
            ->values()
            ->all();
    }

    /** The next sibling position under a parent (append to the end). */
    private function nextPosition(?int $parentId): int
    {
        return (int) Category::where('parent_id', $parentId)->max('position') + 1;
    }

    /**
     * A category plus all its descendants, from a flat collection.
     *
     * @param  Collection<int, Category>  $all
     * @return Collection<int, Category>
     */
    private function subtree(Collection $all, int $rootId): Collection
    {
        $result = $all->where('id', $rootId);

        foreach ($all->where('parent_id', $rootId) as $child) {
            $result = $result->merge($this->subtree($all, $child->id));
        }

        return $result;
    }
}
