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
        Schema::table('daily_programs', function (Blueprint $table) {
            // Per-date override of the weekday homework default.
            $table->time('homework_start')->nullable()->after('activity');
            $table->time('homework_end')->nullable()->after('homework_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_programs', function (Blueprint $table) {
            $table->dropColumn(['homework_start', 'homework_end']);
        });
    }
};
