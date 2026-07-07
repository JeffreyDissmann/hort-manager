<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qualifies a "geht allein" pickup time: whether the child leaves by / exactly at /
     * from that time. Null = the legacy "exactly at" meaning; only used for sent_home.
     */
    public function up(): void
    {
        Schema::table('daily_departures', function (Blueprint $table) {
            $table->string('time_qualifier')->nullable()->after('planned_time');
        });
    }

    public function down(): void
    {
        Schema::table('daily_departures', function (Blueprint $table) {
            $table->dropColumn('time_qualifier');
        });
    }
};
