<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Signing a child up for a Ferienbetreuung day *is* planning their day: the
        // attendance record is the child's DailyDeparture for that date, seeded with
        // the day's end time. No separate registration table — a care day is a normal
        // Hort day whose roster comes from the sign-ups instead of the Stammplan.
        //
        // „Has this family answered at all?" can't be read from those rows, though:
        // picking no days is a valid answer that leaves nothing behind. Hence a marker
        // per (period, child), so the deadline reminder chases only silent families.
        Schema::create('holiday_care_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holiday_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at');
            $table->timestamps();

            $table->unique(['holiday_period_id', 'child_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_care_answers');
    }
};
