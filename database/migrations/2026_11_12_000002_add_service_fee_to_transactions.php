<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya transaksi (fee) untuk paket pay-per-transaction.
 *
 * Dihitung di server saat checkout (TransactionFeeService) dan TIDAK
 * menambah grand_total pelanggan — ini biaya pemakaian platform yang
 * dibebankan ke akun merchant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('service_fee')->default(0)->after('shipping_cost');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('service_fee');
        });
    }
};
