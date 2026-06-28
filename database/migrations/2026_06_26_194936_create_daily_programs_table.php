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
        // One Hort-wide program per day: lunch and the general activity.
        Schema::create('daily_programs', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('lunch')->nullable();    // Mittagessen
            $table->string('activity')->nullable(); // Tagesaktivität
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_programs');
    }
};
