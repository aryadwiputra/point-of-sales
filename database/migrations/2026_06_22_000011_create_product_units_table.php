<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained();
            $table->boolean('is_base')->default(false);
            $table->decimal('conversion_factor', 15, 4)->default(1.0000);
            $table->bigInteger('buy_price');
            $table->bigInteger('sell_price');
            $table->string('barcode', 100)->nullable();
            $table->string('sku_suffix', 20)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
