<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dine_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dine_session_id')->constrained('dine_sessions')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('status', 20)->default('open');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('grand_total')->default(0);
            $table->foreignId('transaction_id')->nullable()->unique()->constrained('transactions')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['dine_session_id', 'sequence']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dine_bills');
    }
};
