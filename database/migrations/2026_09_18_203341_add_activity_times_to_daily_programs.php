<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An Aktivität may carry a time window („Waldtag 09:00–12:00"). Optional by
     * design: most entries are just „was heute läuft", so both null = untimed.
     */
    public function up(): void
    {
        Schema::table('daily_programs', function (Blueprint $table) {
            $table->time('activity_start')->nullable()->after('activity');
            $table->time('activity_end')->nullable()->after('activity_start');
        });
    }

    public function down(): void
    {
        Schema::table('daily_programs', function (Blueprint $table) {
            $table->dropColumn(['activity_start', 'activity_end']);
        });
    }
};
