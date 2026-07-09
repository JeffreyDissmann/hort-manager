<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\ExcursionObserver;
use Database\Factories\ExcursionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

#[ObservedBy([ExcursionObserver::class])]
class Excursion extends Model
{
    /** @use HasFactory<ExcursionFactory> */
    use HasFactory;

    /**
     * Excursions whose RSVP deadline falls today.
     *
     * @param  Builder<Excursion>  $query
     */
    public function scopeDueToday(Builder $query): void
    {
        $query->whereDate('rsvp_deadline', today());
    }

    protected $fillable = [
        'name',
        'date',
        'depart_at',
        'return_at',
        'note',
        'rsvp_deadline',
        'departed_at',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'rsvp_deadline' => 'date:Y-m-d',
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
     * Every invited child plus their poll answer (response/answered_by/answered_at).
     *
     * @return BelongsToMany<Child, $this>
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class)
            ->withPivot(['response', 'answered_by', 'answered_at']);
    }

    /**
     * Children whose parents said yes — the actual trip participants.
     *
     * @return BelongsToMany<Child, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->children()->wherePivot('response', true);
    }

    /** Whether parents can still answer the poll (deadline inclusive, or no deadline). */
    public function pollIsOpen(): bool
    {
        return $this->rsvp_deadline === null
            || $this->rsvp_deadline->endOfDay()->isFuture();
    }

    /**
     * The invited children ordered for display: joining first, then still-undecided,
     * then not coming — each group alphabetical. Reads the loaded `children` relation.
     *
     * @return Collection<int, Child>
     */
    public function childrenByStatus(): Collection
    {
        $rank = fn (Child $c) => match (true) {
            $c->pivot->response === null => 1,
            (bool) $c->pivot->response => 0,
            default => 2,
        };

        return $this->children
            ->sortBy([
                fn (Child $a, Child $b) => $rank($a) <=> $rank($b),
                fn (Child $a, Child $b) => mb_strtolower($a->name) <=> mb_strtolower($b->name),
            ])
            ->values();
    }
}
