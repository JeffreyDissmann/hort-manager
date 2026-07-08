<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * „Geht mit einem Kind mit": the child leaves together with `companion_child_id`,
     * whose pickup time it mirrors. When the companion themselves goes home alone the
     * companion's family must confirm — `companion_confirmed` is null while pending,
     * true/false once answered. Only used with the `with_child` planned method.
     */
    public function up(): void
    {
        Schema::table('daily_departures', function (Blueprint $table) {
            $table->foreignId('companion_child_id')->nullable()->after('planned_method')->constrained('children')->nullOnDelete();
            $table->boolean('companion_confirmed')->nullable()->after('companion_child_id');
            $table->foreignId('companion_confirmed_by')->nullable()->after('companion_confirmed')->constrained('users')->nullOnDelete();
            $table->timestamp('companion_confirmed_at')->nullable()->after('companion_confirmed_by');
        });
    }

    public function down(): void
    {
        Schema::table('daily_departures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('companion_child_id');
            $table->dropConstrainedForeignId('companion_confirmed_by');
            $table->dropColumn(['companion_confirmed', 'companion_confirmed_at']);
        });
    }
};
