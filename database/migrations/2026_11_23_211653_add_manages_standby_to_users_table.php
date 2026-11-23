<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * „Springer-Koordination": the person who fills the Springerdienst list. Usually a
     * parent holding that Amt — neither staff nor admin — so it is an axis of its own,
     * like accounting access.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('manages_standby')->default(false)->after('accounting_access');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('manages_standby');
        });
    }
};
