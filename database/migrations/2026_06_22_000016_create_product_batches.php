<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('batch_number', 100);
            $table->date('expired_at')->nullable();
            $table->date('received_at');
            $table->integer('stock')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'warehouse_id', 'batch_number']);
        });

        Schema::table('transaction_details', function (Blueprint $table) {
            $table->foreignId('product_batch_id')->nullable()->constrained('product_batches');
        });

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->string('batch_number', 100)->nullable();
            $table->date('expired_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropColumn(['batch_number', 'expired_at']);
        });
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropForeign(['product_batch_id']);
            $table->dropColumn('product_batch_id');
        });
        Schema::dropIfExists('product_batches');
    }
};
