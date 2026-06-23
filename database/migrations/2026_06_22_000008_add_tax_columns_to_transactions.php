<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->nullable()->default(11.00);
            $table->bigInteger('tax_total')->default(0);
            $table->string('customer_npwp', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_total', 'customer_npwp']);
        });
    }
};
