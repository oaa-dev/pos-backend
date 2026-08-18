<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_drawer_session_id')->constrained()->cascadeOnDelete();
            $table->string('type');

            // Always positive; the type decides the direction. Storing a signed
            // amount as well would let the two disagree.
            $table->decimal('amount', 12, 2);

            $table->string('reason')->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['cash_drawer_session_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
