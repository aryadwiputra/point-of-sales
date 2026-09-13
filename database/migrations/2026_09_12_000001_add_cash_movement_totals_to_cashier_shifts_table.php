<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            if (! Schema::hasColumn('cashier_shifts', 'cash_in_total')) {
                $table->unsignedBigInteger('cash_in_total')->default(0)->after('cash_refund_total');
            }

            if (! Schema::hasColumn('cashier_shifts', 'cash_out_total')) {
                $table->unsignedBigInteger('cash_out_total')->default(0)->after('cash_in_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            if (Schema::hasColumn('cashier_shifts', 'cash_out_total')) {
                $table->dropColumn('cash_out_total');
            }

            if (Schema::hasColumn('cashier_shifts', 'cash_in_total')) {
                $table->dropColumn('cash_in_total');
            }
        });
    }
};
