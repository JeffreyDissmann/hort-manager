<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A free-text hint for a category, shown in the editor and fed to the AI to
     * improve categorization (e.g. „Essensgeld = monatlicher Beitrag fürs Essen").
     */
    public function up(): void
    {
        Schema::table('accounting_categories', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_categories', function (Blueprint $table) {
            $table->dropColumn('comment');
        });
    }
};
