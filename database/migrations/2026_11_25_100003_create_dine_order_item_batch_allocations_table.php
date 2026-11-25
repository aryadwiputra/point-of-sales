<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dine_order_item_batch_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dine_order_item_id')->constrained('dine_order_items')->cascadeOnDelete();
            $table->foreignId('product_batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->integer('qty');
            $table->timestamps();

            $table->unique(
                ['dine_order_item_id', 'product_batch_id'],
                'diba_item_batch_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dine_order_item_batch_allocations');
    }
};
