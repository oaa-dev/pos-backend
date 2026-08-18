<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_users', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->boolean('is_owner')->default(false);

            $table->timestamps();

            // One membership row per person per store. Ownership is a column
            // on that row, not a second row.
            $table->unique(['store_id', 'user_id']);

            $table->index(['store_id', 'is_owner']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_users');
    }
};
