<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_composite')->default(false);
        });

        Schema::create('composite_product_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('composite_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products');
            $table->decimal('qty', 15, 4)->default(1);
            $table->timestamps();
            $table->unique(['composite_product_id', 'component_product_id'], 'composite_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('composite_product_items');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_composite');
        });
    }
};
