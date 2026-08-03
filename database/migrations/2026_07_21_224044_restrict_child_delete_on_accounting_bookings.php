<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A child with contributions attributed to it must not be deletable — otherwise
     * the payments would silently lose their reference. Block the delete at the DB
     * level (the app surfaces a friendly message before it ever gets here).
     */
    public function up(): void
    {
        Schema::table('accounting_bookings', function (Blueprint $table) {
            $table->dropForeign(['counterparty_child_id']);
            $table->foreign('counterparty_child_id')->references('id')->on('children')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounting_bookings', function (Blueprint $table) {
            $table->dropForeign(['counterparty_child_id']);
            $table->foreign('counterparty_child_id')->references('id')->on('children')->nullOnDelete();
        });
    }
};
