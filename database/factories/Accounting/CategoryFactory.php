<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Enums\CategoryDirection;
use App\Models\Accounting\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->word(),
            'direction' => CategoryDirection::Income,
            'position' => 0,
            'active' => true,
        ];
    }

    public function income(): static
    {
        return $this->state(['direction' => CategoryDirection::Income]);
    }

    public function expense(): static
    {
        return $this->state(['direction' => CategoryDirection::Expense]);
    }

    /** Nest under a parent, inheriting its direction. */
    public function childOf(Category $parent): static
    {
        return $this->state(['parent_id' => $parent->id, 'direction' => $parent->direction]);
    }
}
