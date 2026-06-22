<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
        });

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
        });
    }

    public function down(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
