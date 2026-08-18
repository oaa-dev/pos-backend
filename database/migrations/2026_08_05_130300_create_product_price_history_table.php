<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_unit_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('effective_at');
            $table->timestamps();

            $table->index(['product_unit_id', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_history');
    }
};
