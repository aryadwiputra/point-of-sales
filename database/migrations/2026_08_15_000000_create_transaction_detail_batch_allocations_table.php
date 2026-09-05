<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_detail_batch_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_batch_id')->constrained()->cascadeOnDelete();
            $table->integer('qty');
            $table->timestamps();

            $table->unique(['transaction_detail_id', 'product_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_detail_batch_allocations');
    }
};
