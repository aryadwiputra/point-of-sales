<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_station_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('dine_order_id')->nullable()->constrained('dine_orders')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('status', 16)->default('open');
            $table->string('order_type', 20);
            $table->timestamp('placed_at');
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['dine_order_id', 'kitchen_station_id']);
            $table->unique(['transaction_id', 'kitchen_station_id']);
            $table->index(['outlet_id', 'status', 'placed_at']);
            $table->index(['kitchen_station_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_tickets');
    }
};
