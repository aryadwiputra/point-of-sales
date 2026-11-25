<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('created_at', 'transactions_created_at_index');
            $table->index(['cashier_id', 'created_at'], 'transactions_cashier_created_at_index');
            $table->index(['customer_id', 'created_at'], 'transactions_customer_created_at_index');
            $table->index('invoice', 'transactions_invoice_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('no_telp', 'customers_no_telp_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_created_at_index');
            $table->dropIndex('transactions_cashier_created_at_index');
            $table->dropIndex('transactions_customer_created_at_index');
            $table->dropIndex('transactions_invoice_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_no_telp_index');
        });
    }
};
