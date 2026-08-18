<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->morphs('addressable');
            $table->foreignId('region_id')->constrained();
            $table->foreignId('province_id')->nullable()->constrained();
            $table->foreignId('city_id')->constrained();
            $table->foreignId('barangay_id')->constrained();
            $table->string('address_line')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('type')->default('primary');
            $table->boolean('is_default')->default(true);
            $table->timestamps();

            $table->index(['addressable_type', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
