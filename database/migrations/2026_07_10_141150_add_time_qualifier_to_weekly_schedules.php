<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets the Stammplan say whether a „geht allein" time means bis / genau um / ab
     * (App\Enums\TimeQualifier), mirroring the per-day Wochenplan override. Null =
     * the implicit „genau um".
     */
    public function up(): void
    {
        Schema::table('weekly_schedules', function (Blueprint $table) {
            $table->string('time_qualifier')->nullable()->after('method');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_schedules', function (Blueprint $table) {
            $table->dropColumn('time_qualifier');
        });
    }
};
