<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slack_id')->nullable()->unique()->after('email');
            $table->string('avatar')->nullable()->after('slack_id');
            // Slack-provisioned users sign in via Slack and have no password.
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['slack_id', 'avatar']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
