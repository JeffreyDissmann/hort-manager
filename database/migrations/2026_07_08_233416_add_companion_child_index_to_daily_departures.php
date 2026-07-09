<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SQLite doesn't auto-index foreign keys. `companion_child_id` is filtered by the
     * companion lookups (Child::accompaniedDepartures, CompanionNotes, EffectivePlan),
     * usually alongside `date` — a composite index matches that access pattern.
     */
    public function up(): void
    {
        Schema::table('daily_departures', function (Blueprint $table) {
            $table->index(['companion_child_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_departures', function (Blueprint $table) {
            $table->dropIndex(['companion_child_id', 'date']);
        });
    }
};
