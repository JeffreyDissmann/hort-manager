<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use App\Models\Accounting\Category;
use Illuminate\Support\Collection;

/** Flattens the category tree into tree-ordered options for selects and reports. */
class CategoryOptions
{
    /**
     * Tree-ordered category options with depth and a „Parent › Child" path label.
     *
     * @return list<array{id:int, name:string, path:string, direction:string, depth:int, active:bool}>
     */
    public static function flat(bool $onlyActive = true): array
    {
        $categories = Category::query()
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $options = [];
        self::walk($categories, null, [], $options);

        return $options;
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  list<string>  $trail
     * @param  list<array<string, mixed>>  $options
     */
    private static function walk(Collection $categories, ?int $parentId, array $trail, array &$options): void
    {
        foreach ($categories->where('parent_id', $parentId) as $category) {
            $path = [...$trail, $category->name];

            $options[] = [
                'id' => $category->id,
                'name' => $category->name,
                'path' => implode(' › ', $path),
                'direction' => $category->direction->value,
                'depth' => count($trail),
                'active' => $category->active,
            ];

            self::walk($categories, $category->id, $path, $options);
        }
    }
}
