<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per offered day of a Ferienbetreuung: whether the day is on offer at
        // all, and between when. Generated from the period's weekdays with the
        // Hort-wide default times. Deleting the row un-offers that day.
        //
        // What *happens* that day (Aktivität, Essen) stays in DailyProgram, like every
        // other Hort day — otherwise it would have to be replumbed into the board,
        // the Wochenplan and the Wochenüberblick all over again.
        Schema::create('holiday_care_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holiday_period_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            // A day is offered at most once, and the board looks days up by date.
            $table->unique(['holiday_period_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_care_days');
    }
};
