<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A family's request to exchange one of its Springerdienst dates for another
     * family's. The children holding both dates at request time are kept: if either
     * date changes hands before the answer (the coordinator reassigns it, another swap
     * goes through), the request no longer describes the list and is treated as void.
     */
    public function up(): void
    {
        Schema::create('standby_swaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offered_duty_id')->constrained('standby_duties')->cascadeOnDelete();
            $table->foreignId('offered_child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('wanted_duty_id')->constrained('standby_duties')->cascadeOnDelete();
            $table->foreignId('wanted_child_id')->constrained('children')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'offered_duty_id']);
            $table->index(['status', 'wanted_duty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standby_swaps');
    }
};
