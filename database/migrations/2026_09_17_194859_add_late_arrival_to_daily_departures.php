<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * „Kommt später": an optional, day-only arrival time plus its reason (Arzttermin,
     * Training …). Informational — nobody marks the arrival; null = arrives as usual.
     */
    public function up(): void
    {
        Schema::table('daily_departures', function (Blueprint $table) {
            $table->time('arrives_at')->nullable()->after('time_qualifier');
            $table->string('arrival_note')->nullable()->after('arrives_at');
        });
    }

    public function down(): void
    {
        Schema::table('daily_departures', function (Blueprint $table) {
            $table->dropColumn(['arrives_at', 'arrival_note']);
        });
    }
};
