<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
        });

        Schema::table('goods_receivings', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
        });

        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
        });

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
        });
    }

    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']); $table->dropColumn('warehouse_id');
        });
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']); $table->dropColumn('warehouse_id');
        });
        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']); $table->dropColumn('warehouse_id');
        });
        Schema::table('goods_receivings', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']); $table->dropColumn('warehouse_id');
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']); $table->dropColumn('warehouse_id');
        });
    }
};
