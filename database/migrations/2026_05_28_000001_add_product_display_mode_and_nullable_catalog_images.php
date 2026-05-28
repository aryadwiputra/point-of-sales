<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'product_display_mode'],
            [
                'value' => 'image_grid',
                'description' => 'Mode tampilan produk dan kategori',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Schema::table('products', function (Blueprint $table) {
            $table->string('image')->nullable()->change();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('image')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'product_display_mode')->delete();

        Schema::table('products', function (Blueprint $table) {
            $table->string('image')->nullable(false)->change();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('image')->nullable(false)->change();
        });
    }
};
