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
            // The rows skipped as duplicates, kept so the user can import genuine ones.
            $table->json('skipped_rows')->nullable()->after('duplicate_count');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_imports', function (Blueprint $table) {
            $table->dropColumn('skipped_rows');
        });
    }
};
