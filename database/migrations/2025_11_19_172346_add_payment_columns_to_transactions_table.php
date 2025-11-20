<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payment_method')->default('cash')->after('grand_total');
            $table->string('payment_status')->default('paid')->after('payment_method');
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->text('payment_url')->nullable()->after('payment_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_status',
                'payment_reference',
                'payment_url',
            ]);
        });
    }
};
