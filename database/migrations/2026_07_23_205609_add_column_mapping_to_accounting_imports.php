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
        Schema::table('accounting_imports', function (Blueprint $table) {
            // The decoded CSV awaiting a confirmed column mapping ({delimiter, header,
            // rows}); cleared once the mapping is applied and the drafts are created.
            $table->json('pending_columns')->nullable()->after('skipped_rows');
            // The confirmed field → column-index map (kept for the record).
            $table->json('column_mapping')->nullable()->after('pending_columns');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_imports', function (Blueprint $table) {
            $table->dropColumn(['pending_columns', 'column_mapping']);
        });
    }
};
