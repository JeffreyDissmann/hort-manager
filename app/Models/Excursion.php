<?php

namespace App\Models;

use Database\Factories\ExcursionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Excursion extends Model
{
    /** @use HasFactory<ExcursionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'depart_at',
        'return_at',
        'note',
        'departed_at',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'departed_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /** Live trip state: planned (not left yet) → away (unterwegs) → back (returned). */
    public function state(): string
    {
        if ($this->returned_at) {
            return 'back';
        }

        return $this->departed_at ? 'away' : 'planned';
    }

    /**
     * @return BelongsToMany<Child, $this>
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class);
    }
}
