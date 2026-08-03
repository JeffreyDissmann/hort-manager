<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_departures', function (Blueprint $table) {
            // Why this row exists. A Ferienbetreuung sign-up *is* the child's plan for
            // that day, so without this it is indistinguishable from an ordinary
            // same-day override — and every write touching the date (un-offering a day,
            // a closure moving over it, withdrawing) hit whichever rows it found.
            //
            // Null on a departed row: the day may be un-offered afterwards, and history
            // outlives the offer.
            $table->foreignId('holiday_care_day_id')
                ->nullable()
                ->after('date')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_departures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('holiday_care_day_id');
        });
    }
};
