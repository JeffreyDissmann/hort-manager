<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holiday_periods', function (Blueprint $table) {
            // Ferienbetreuung only: the day parents must have opted in by. Null = open
            // ended. A `closed` period has nothing to register for and leaves it null.
            $table->date('registration_deadline')->nullable()->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('holiday_periods', function (Blueprint $table) {
            $table->dropColumn('registration_deadline');
        });
    }
};
