<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('min_stock')->default(0);
            $table->integer('max_stock')->default(0);
        });

        Schema::table('product_warehouse', function (Blueprint $table) {
            $table->integer('min_stock')->default(0);
            $table->integer('max_stock')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('product_warehouse', function (Blueprint $table) {
            $table->dropColumn(['min_stock', 'max_stock']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['min_stock', 'max_stock']);
        });
    }
};
