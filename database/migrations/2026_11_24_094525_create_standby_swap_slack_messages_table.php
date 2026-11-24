<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One Slack DM per guardian per Springerdienst swap request, so every copy can be
     * chat.updated (buttons replaced by the result) once anyone answers or it lapses.
     */
    public function up(): void
    {
        Schema::create('standby_swap_slack_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standby_swap_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('ts');
            $table->timestamps();

            $table->unique(['standby_swap_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standby_swap_slack_messages');
    }
};
