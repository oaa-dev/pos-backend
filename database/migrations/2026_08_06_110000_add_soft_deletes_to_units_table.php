<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Soft delete rather than hard: `products.base_unit_id` is
        // constrained without an onDelete clause, so it RESTRICTs — deleting a
        // unit in use raises a raw FK error. Keeping the row means no DELETE is
        // ever issued and the reference stays valid, at the cost of every
        // relation needing withTrashed().
        Schema::table('units', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
