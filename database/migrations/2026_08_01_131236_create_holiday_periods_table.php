<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named Hort-wide date ranges that override normal operation. `closed` = the
        // Hort is shut; `care` (Ferienbetreuung) is reserved for the follow-up feature.
        Schema::create('holiday_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('closed');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('note')->nullable();
            $table->timestamps();

            // Every lookup is „does any period cover this date/range?".
            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_periods');
    }
};
