<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One bank-statement CSV upload. Its draft bookings reference it, so the
     * review queue can show per-batch progress and duplicate counts.
     */
    public function up(): void
    {
        Schema::create('accounting_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounting_accounts')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_imports');
    }
};
