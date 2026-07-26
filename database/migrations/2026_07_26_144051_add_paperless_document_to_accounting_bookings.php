<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A booking can reference one document in the external Paperless-ngx archive.
     * The id links it; the title is cached so the ledger can show a paperclip label
     * without a Paperless round-trip per row.
     */
    public function up(): void
    {
        Schema::table('accounting_bookings', function (Blueprint $table) {
            $table->unsignedInteger('paperless_document_id')->nullable()->after('comment');
            $table->string('paperless_document_title')->nullable()->after('paperless_document_id');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_bookings', function (Blueprint $table) {
            $table->dropColumn(['paperless_document_id', 'paperless_document_title']);
        });
    }
};
