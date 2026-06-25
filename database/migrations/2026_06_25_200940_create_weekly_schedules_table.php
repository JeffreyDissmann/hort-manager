<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('weekly_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            // 1 = Montag … 5 = Freitag (ISO weekday, matching CarbonInterface::dayOfWeekIso).
            $table->unsignedTinyInteger('weekday');
            // Planned departure time, e.g. "16:00". Null = no Hort that weekday.
            $table->time('planned_time')->nullable();
            // DepartureMethod: abgeholt | nach_hause. Null = not yet decided.
            $table->string('method')->nullable();
            $table->timestamps();

            $table->unique(['child_id', 'weekday']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_schedules');
    }
};
