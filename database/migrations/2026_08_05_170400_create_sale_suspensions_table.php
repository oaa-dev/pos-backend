<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_suspensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('label')->nullable();

            // The cart as JSON. A parked cart holds no stock — nothing moves
            // until the sale completes — so a stale one costs nothing and can
            // never oversell.
            $table->json('payload');

            $table->timestamp('suspended_at');
            $table->timestamp('resumed_at')->nullable();
            $table->string('status')->default('suspended');
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_suspensions');
    }
};
