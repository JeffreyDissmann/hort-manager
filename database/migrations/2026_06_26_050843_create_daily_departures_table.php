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
        // One row per child per day: the effective plan (seeded from the
        // Stammplan, same-day overridable) plus the live departure state.
        Schema::create('daily_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->date('date');
            $table->string('status')->default('present'); // DepartureStatus
            $table->time('planned_time')->nullable();
            $table->string('planned_method')->nullable(); // DepartureMethod
            $table->timestamp('left_at')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['child_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_departures');
    }
};
