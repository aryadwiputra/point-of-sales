<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bihaju_bindings')) {
            Schema::create('bihaju_bindings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                // Outlet/toko BPOS yang diikatkan ke merchant Bihaju.
                $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
                // ID toko di sisi BPOS yang dilihat Bihaju (unik global — satu partner).
                $table->string('external_ref', 100)->unique();
                // Status permohonan sebelum merchant menyetujui.
                $table->string('status', 20)->default('pending')->index();
                $table->string('bind_code', 64)->nullable();
                // URL siap-scan yang dikembalikan Bihaju (masuk ke QR).
                $table->text('bind_url')->nullable();
                $table->timestamp('bind_code_expires_at')->nullable();
                // merchant_api_key diterima via webhook; cast encrypted di model.
                $table->text('merchant_api_key')->nullable();
                $table->unsignedBigInteger('merchant_id')->nullable();
                $table->string('merchant_name')->nullable();
                $table->string('merchant_city')->nullable();
                $table->string('merchant_status')->nullable();
                $table->unsignedBigInteger('merchant_balance')->nullable();
                // Snapshot payload callback terakhir (tanpa rahasia).
                $table->json('last_callback')->nullable();
                $table->timestamp('bound_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('bihaju_transactions')) {
            Schema::create('bihaju_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('binding_id')->nullable()->constrained('bihaju_bindings')->nullOnDelete();
                // Transaksi BPOS terkait (penjualan tunai/piutang yang dibayar).
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                // Idempotency key — harus unik di sisi Bihaju per partner.
                $table->string('external_ref', 100)->unique();
                $table->unsignedBigInteger('amount')->default(0);
                $table->string('member_code', 60)->nullable();
                $table->string('status', 20)->default('pending')->index();
                $table->string('bihaju_transaction_code', 60)->nullable();
                $table->unsignedBigInteger('balance_after')->nullable();
                $table->unsignedInteger('attempts')->default(0);
                // Rincian komisi dari Bihaju (merchant_commission, member_cashback, platform_fee).
                $table->json('breakdown')->nullable();
                $table->json('response')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index('transaction_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bihaju_transactions');
        Schema::dropIfExists('bihaju_bindings');
    }
};
