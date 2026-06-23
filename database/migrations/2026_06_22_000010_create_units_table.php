<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 50);
            $table->string('symbol', 10);
            $table->timestamps();
        });

        DB::table('units')->insert([
            ['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'BOX', 'name' => 'Box', 'symbol' => 'box', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'KARTON', 'name' => 'Karton', 'symbol' => 'karton', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'KG', 'name' => 'Kilogram', 'symbol' => 'kg', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'LITER', 'name' => 'Liter', 'symbol' => 'ltr', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'METER', 'name' => 'Meter', 'symbol' => 'm', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'PAK', 'name' => 'Pak', 'symbol' => 'pak', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'DUS', 'name' => 'Dus', 'symbol' => 'dus', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
