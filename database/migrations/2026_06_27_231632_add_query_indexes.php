<?php

declare(strict_types=1);

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
        // Date-only lookups on the board/Wochenplan and the RSVP-pending counts.
        Schema::table('daily_departures', function (Blueprint $table) {
            $table->index('date');
        });

        Schema::table('excursions', function (Blueprint $table) {
            $table->index('date');
        });

        Schema::table('child_excursion', function (Blueprint $table) {
            $table->index('response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_departures', function (Blueprint $table) {
            $table->dropIndex(['date']);
        });

        Schema::table('excursions', function (Blueprint $table) {
            $table->dropIndex(['date']);
        });

        Schema::table('child_excursion', function (Blueprint $table) {
            $table->dropIndex(['response']);
        });
    }
};
