<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();

            // Null means the discount applies to the whole sale rather than to
            // one line.
            $table->foreignId('sale_item_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type');
            $table->decimal('percentage', 5, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('amount_before', 12, 2)->default(0);
            $table->decimal('amount_after', 12, 2)->default(0);

            // The senior/PWD logbook the law asks for.
            $table->string('id_number')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('signature_path')->nullable();

            $table->string('reason')->nullable();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sale_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_discounts');
    }
};
