<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The tenant boundary. One row per paying customer — a sari-sari
        // business, which may run several branches under it.
        //
        // Soft-deleted rather than removed: a cancelled store's sales, utang
        // ledger, and stock history are the customer's records, not ours, and
        // deleting the row would orphan every one of them.
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Stable, human-readable handle. Unique globally because it is the
            // one identifier that must resolve without already knowing a store.
            $table->string('slug')->unique();

            // The person who signed up, kept separate from the owner user
            // account so a store can change hands without losing who opened it.
            $table->string('owner_name')->nullable();

            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
