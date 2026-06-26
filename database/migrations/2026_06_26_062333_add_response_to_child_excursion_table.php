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
        Schema::table('child_excursion', function (Blueprint $table) {
            // RSVP per child: null = offen, true = nimmt teil, false = nimmt nicht teil.
            $table->boolean('response')->nullable()->after('excursion_id');
            $table->foreignId('answered_by')->nullable()->after('response')->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable()->after('answered_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('child_excursion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('answered_by');
            $table->dropColumn(['response', 'answered_at']);
        });
    }
};
