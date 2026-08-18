<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Whether half of this unit is a real thing. Half a kilo of bigas is;
        // half an itlog is not. Without it a reorder point comes out as
        // "1.806 piraso", which cannot be counted on a shelf.
        Schema::table('units', function (Blueprint $table) {
            $table->boolean('allows_fraction')->default(false)->after('abbreviation');
        });

        // The three seeded units that are measured rather than counted.
        DB::table('units')->whereIn('name', ['kilo', 'gramo', 'litro'])->update(['allows_fraction' => true]);
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('allows_fraction');
        });
    }
};
