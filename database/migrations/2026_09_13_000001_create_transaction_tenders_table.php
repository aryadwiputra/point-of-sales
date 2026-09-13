<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_tenders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('method', 30);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('cash_received')->nullable();
            $table->unsignedBigInteger('change')->default(0);
            $table->foreignId('bank_account_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('payment_status', 20)->default('paid');
            $table->string('payment_reference')->nullable();
            $table->text('payment_url')->nullable();
            $table->text('qr_string')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'payment_status']);
            $table->index(['method', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_tenders');
    }
};
