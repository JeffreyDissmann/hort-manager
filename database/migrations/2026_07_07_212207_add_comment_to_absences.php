<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An optional reason note for the absence (e.g. „Urlaub", „Fieber"). Required by
     * the Wochenplan editor, optional from the board's quick-report and the assistant.
     */
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->string('comment')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->dropColumn('comment');
        });
    }
};
