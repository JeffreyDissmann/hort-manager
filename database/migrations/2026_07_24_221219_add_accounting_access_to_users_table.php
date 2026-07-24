<?php

declare(strict_types=1);

use App\Enums\AccountingAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounting access as an independent axis (none/read/write), alongside the
     * Erzieher/Parent role and the admin flag. Existing admins are backfilled to
     * „write" so nobody loses the accounting access they have today; from here on it
     * is assigned separately and is not tied to admin.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('accounting_access')->default(AccountingAccess::None->value)->after('is_admin');
        });

        DB::table('users')->where('is_admin', true)->update(['accounting_access' => AccountingAccess::Write->value]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('accounting_access');
        });
    }
};
