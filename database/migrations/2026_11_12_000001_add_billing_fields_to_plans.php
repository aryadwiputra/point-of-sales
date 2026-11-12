<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier keempat: paket tanpa langganan bulanan/tahunan — bayar per transaksi.
 *
 * Semua nilai biaya diset dari menu Superadmin (PlanManagementController),
 * bukan hardcode. Kolom fee_* hanya berlaku bila billing_type = per_transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('billing_type', 20)->default('subscription')->after('price_yearly');
            $table->string('fee_type', 20)->nullable()->after('billing_type'); // percent | fixed
            $table->decimal('fee_value', 12, 2)->default(0)->after('fee_type'); // % atau nominal Rp
            $table->decimal('fee_min', 12, 2)->default(0)->after('fee_value'); // biaya minimum per transaksi
            $table->decimal('fee_max', 12, 2)->nullable()->after('fee_min'); // cap biaya per transaksi (null = tanpa batas)
            $table->string('fee_rounding', 20)->default('none')->after('fee_max'); // none | up | down
            $table->json('fee_methods')->nullable()->after('fee_rounding'); // null/kosong = semua metode bayar
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'billing_type',
                'fee_type',
                'fee_value',
                'fee_min',
                'fee_max',
                'fee_rounding',
                'fee_methods',
            ]);
        });
    }
};
