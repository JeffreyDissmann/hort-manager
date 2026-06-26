<?php

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
        Schema::table('excursions', function (Blueprint $table) {
            // Live state, set manually by staff on the trip day.
            $table->timestamp('departed_at')->nullable()->after('return_at');
            $table->timestamp('returned_at')->nullable()->after('departed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('excursions', function (Blueprint $table) {
            $table->dropColumn(['departed_at', 'returned_at']);
        });
    }
};
