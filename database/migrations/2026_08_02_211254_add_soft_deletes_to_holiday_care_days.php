<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A day staff stop offering leaves a tombstone, so re-saving the Ferienbetreuung
 * can tell „removed on purpose" from „never created" — the latter being a day a
 * Schließzeit hid, which is meant to come back once the closure is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holiday_care_days', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('holiday_care_days', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
