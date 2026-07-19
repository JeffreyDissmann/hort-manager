<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A bank or cash account (Konto, Bar-Kasse). Balance = opening balance plus
     * the sum of its confirmed bookings, so an opening balance anchors it to reality.
     */
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('iban')->nullable();
            $table->integer('opening_balance_cents')->default(0);
            $table->date('opening_balance_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_accounts');
    }
};
