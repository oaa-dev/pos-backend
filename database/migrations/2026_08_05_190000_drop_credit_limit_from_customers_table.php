<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A hard credit limit does not match how a sari-sari actually decides.
     * The owner judges each suki case by case, and `is_blocked` already
     * expresses the only rule that is really enforced: "bawal muna umutang".
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('credit_limit');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_limit', 12, 2)->default(0)->after('branch_id');
        });
    }
};
