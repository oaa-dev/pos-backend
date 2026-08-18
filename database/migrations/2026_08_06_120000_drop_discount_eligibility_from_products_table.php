<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-product eligibility is gone: a statutory discount now applies to
        // the whole cart rather than to flagged lines. The column's only
        // reader was SaleService::eligibleSubtotal(), deleted with it.
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('discount_eligibility');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('discount_eligibility')->default('eligible')->after('track_stock');
        });
    }
};
