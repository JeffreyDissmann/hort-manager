<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A child's Hort enrolment period: active_from (start) and active_until (leave
     * date, null = still enrolled). Existing children are backfilled to "enrolled
     * since they were created, open-ended".
     */
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->date('active_from')->nullable()->after('date_of_birth');
            $table->date('active_until')->nullable()->after('active_from');
        });

        DB::table('children')->whereNull('active_from')->update([
            'active_from' => DB::raw('date(created_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn(['active_from', 'active_until']);
        });
    }
};
