<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One Slack DM per companion-guardian per „geht mit … mit" arrangement, so we can
     * chat.update it (show the result / remove the buttons) once anyone answers.
     */
    public function up(): void
    {
        Schema::create('companion_slack_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_departure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('ts');
            $table->timestamps();

            $table->unique(['daily_departure_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companion_slack_messages');
    }
};
