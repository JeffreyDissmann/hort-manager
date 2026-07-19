<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single ledger entry. `amount_cents` is signed real cash flow (income +,
     * expense −, transfer ±), so an account balance is just the sum of its rows.
     * `import_hash` de-duplicates re-uploaded statement lines per account.
     * `transfer_id` links the two legs of an internal transfer — a plain pointer
     * (no DB-level FK: SQLite can't add one to an already-created table, and the
     * enforced FKs live on accounting_transfers).
     */
    public function up(): void
    {
        Schema::create('accounting_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounting_accounts')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('accounting_categories')->nullOnDelete();
            $table->foreignId('import_id')->nullable()->constrained('accounting_imports')->nullOnDelete();
            $table->unsignedBigInteger('transfer_id')->nullable();

            $table->string('kind');
            $table->string('status')->default('confirmed');
            $table->integer('amount_cents');
            $table->string('currency', 3)->default('EUR');
            $table->date('booking_date');
            $table->date('valuta_date')->nullable();
            $table->text('purpose')->nullable();
            $table->text('comment')->nullable();

            // Counterparty: either a linked app user or a free-text name.
            $table->foreignId('counterparty_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('counterparty_name')->nullable();

            // AI proposals held until a reviewer accepts them.
            $table->foreignId('suggested_category_id')->nullable()->constrained('accounting_categories')->nullOnDelete();
            $table->foreignId('suggested_counterparty_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suggested_counterparty_name')->nullable();
            // When the AI pass produced its suggestions (null = not yet processed).
            $table->timestamp('ai_suggested_at')->nullable();

            $table->string('import_hash')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'import_hash']);
            $table->index(['status', 'booking_date']);
            $table->index('transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_bookings');
    }
};
