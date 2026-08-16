<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dine_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dine_table_id')->constrained('dine_tables')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('access_token')->unique();
            $table->string('status')->default('submitted');
            $table->text('notes')->nullable();
            $table->string('payment_option')->default('pay_at_counter');
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('payment_url')->nullable();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->integer('subtotal')->default(0);
            $table->integer('item_count')->default(0);
            $table->timestamps();
            $table->timestamp('submitted_at')->nullable();

            $table->index('status');
            $table->index('access_token');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dine_orders');
    }
};
