<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dine_order_item_id')->nullable()->constrained('dine_order_items')->nullOnDelete();
            $table->foreignId('transaction_detail_id')->nullable()->constrained('transaction_details')->nullOnDelete();
            $table->string('product_name', 150);
            $table->integer('qty');
            $table->string('modifiers_text', 500)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('status', 16)->default('queued');
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique('dine_order_item_id');
            $table->unique('transaction_detail_id');
            $table->index(['kitchen_ticket_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_ticket_items');
    }
};
