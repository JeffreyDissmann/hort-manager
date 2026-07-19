<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\CategoryDirection;
use Database\Factories\Accounting\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A node in the nested booking-category tree. `direction` is set at the root and
 * inherited by all descendants (enforced when categories are created/moved); a
 * booking may attach to any node.
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $table = 'accounting_categories';

    protected $fillable = [
        'parent_id',
        'name',
        'comment',
        'direction',
        'position',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'direction' => CategoryDirection::class,
            'position' => 'integer',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('position');
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
