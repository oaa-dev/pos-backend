<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('permission_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
