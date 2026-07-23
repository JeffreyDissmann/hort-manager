<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An internal transfer between two own accounts, linking its two booking legs
     * (an expense on the source account and an income on the target). Deleting
     * either leg cascades the transfer away.
     */
    public function up(): void
    {
        Schema::create('accounting_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('out_booking_id')->constrained('accounting_bookings')->cascadeOnDelete();
            $table->foreignId('in_booking_id')->constrained('accounting_bookings')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_transfers');
    }
};
