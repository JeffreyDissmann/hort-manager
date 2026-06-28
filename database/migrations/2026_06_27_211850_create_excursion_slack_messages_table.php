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
        // One Slack DM per guardian per excursion, so we can chat.update it
        // (remove buttons / show the result) once anyone answers.
        Schema::create('excursion_slack_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excursion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('ts');
            $table->timestamps();

            $table->unique(['excursion_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excursion_slack_messages');
    }
};
