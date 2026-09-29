<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Invitations were only ever filtered by enrolment when they were made, so a child
     * who left afterwards stayed invited to trips that happen after their leaving date
     * — their family kept being asked to answer for them. ChildObserver::updated() now
     * keeps that in step; this clears the rows that predate it.
     *
     * Past trips are left alone: they record who was there.
     */
    public function up(): void
    {
        $today = Carbon::today()->toDateString();

        $stale = DB::table('child_excursion')
            ->join('excursions', 'excursions.id', '=', 'child_excursion.excursion_id')
            ->join('children', 'children.id', '=', 'child_excursion.child_id')
            ->whereDate('excursions.date', '>=', $today)
            ->where(function ($query): void {
                // A null active_from means „always active" (as the model scopes read it).
                $query->where(function ($q): void {
                    $q->whereNotNull('children.active_from')
                        ->whereColumn('children.active_from', '>', 'excursions.date');
                })->orWhere(function ($q): void {
                    $q->whereNotNull('children.active_until')
                        ->whereColumn('children.active_until', '<', 'excursions.date');
                });
            })
            ->pluck('child_excursion.id');

        if ($stale->isNotEmpty()) {
            DB::table('child_excursion')->whereIn('id', $stale)->delete();
        }
    }

    /** One-off data repair — the invitations cannot be told apart from valid ones afterwards. */
    public function down(): void {}
};
