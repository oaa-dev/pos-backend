<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Discounts become data. What was SaleDiscountTypeEnum — six hardcoded
        // cases with behaviour attached — is now a row the owner can add to.
        Schema::create('discount_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // What `sale_discounts.type` already stores, so the six seeded
            // rows line up with every historical sale without a data fix.
            $table->string('slug')->unique();

            // Null means the discount is entered as a peso amount rather than
            // a rate — a haggled price-off, not a percentage.
            $table->decimal('percentage', 5, 2)->nullable();

            // Drives the id_number / customer_name logbook the law asks for.
            $table->boolean('requires_identification')->default(false);

            // Statutory rows. CRUD may rename them or switch them off, but not
            // delete them and not change what they are worth — a senior
            // discount quietly re-rated to 15% is indistinguishable from a
            // correct sale afterwards.
            $table->boolean('is_system')->default(false);

            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'slug']);
        });

        // Historical rows keep their `type` slug as written; the FK is the
        // forward path. Nullable because a sale recorded before a type was
        // added has nothing to point at.
        Schema::table('sale_discounts', function (Blueprint $table) {
            $table->foreignId('discount_type_id')
                ->nullable()
                ->after('sale_item_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_discounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_type_id');
        });

        Schema::dropIfExists('discount_types');
    }
};
