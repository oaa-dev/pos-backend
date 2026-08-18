<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('psgc_code')->unique()->nullable();
            $table->string('name');
            $table->boolean('is_city')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
