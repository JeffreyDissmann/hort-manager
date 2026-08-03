<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A booking's counterparty can be a child (income is attributed to the child,
     * not the paying guardian), a user, or free text.
     */
    public function up(): void
    {
        Schema::table('accounting_bookings', function (Blueprint $table) {
            $table->foreignId('counterparty_child_id')->nullable()->after('counterparty_user_id')->constrained('children')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounting_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counterparty_child_id');
        });
    }
};
