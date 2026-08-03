<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How confident the AI suggestion is (low/medium/high) — a blend of the
     * model's self-assessment and deterministic signals. Drives the review badge
     * and riskiest-first ordering. Null for manual/confirmed bookings.
     */
    public function up(): void
    {
        Schema::table('accounting_bookings', function (Blueprint $table) {
            // Small int (0 = low … 2 = high) so the value is also the sort rank.
            $table->unsignedTinyInteger('confidence')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_bookings', function (Blueprint $table) {
            $table->dropColumn('confidence');
        });
    }
};
