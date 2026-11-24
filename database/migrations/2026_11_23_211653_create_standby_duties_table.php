<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * „Springerdienst" — the family on call to cover when Erzieher are ill, one row per
     * filled slot. A normal Hort day has one slot, a Ferienbetreuung day two; how many a
     * date has is derived from the calendar, so an empty slot is simply a missing row.
     *
     * The duty is held by a child (their guardians are the Springer). A child deleted
     * later leaves the row with a null child, which reads as „offen" like a missing row.
     */
    public function up(): void
    {
        Schema::create('standby_duties', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('slot')->default(1);
            $table->foreignId('child_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['date', 'slot']);
            $table->index('child_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standby_duties');
    }
};
