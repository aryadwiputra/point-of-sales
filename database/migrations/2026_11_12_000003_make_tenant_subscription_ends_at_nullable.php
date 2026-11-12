<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket gratis (Rp0, mis. "Bayar Per Transaksi") tidak kedaluwarsa —
 * `ends_at` nullable supaya bisa disimpan tanpa batas waktu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->timestamp('ends_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->timestamp('ends_at')->nullable(false)->change();
        });
    }
};
