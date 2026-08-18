<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // Days until payment is due. Zero means cash on delivery, which is
            // how most sari-sari deliveries actually work.
            $table->unsignedSmallInteger('terms_days')->default(0);

            $table->string('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        // What this supplier charges for a product, so a reorder does not start
        // from a blank cost every time.
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->decimal('last_cost', 12, 4)->default(0);
            $table->unsignedSmallInteger('lead_time_days')->default(0);
            $table->timestamps();

            $table->unique(['supplier_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('suppliers');
    }
};
