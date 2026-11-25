<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dine_order_items', function (Blueprint $table) {
            $table->foreignId('dine_bill_id')->nullable()->constrained('dine_bills')->nullOnDelete();
            $table->bigInteger('unit_cost')->default(0);
            $table->string('tax_type', 20)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->index('dine_bill_id');
        });
    }

    public function down(): void
    {
        Schema::table('dine_order_items', function (Blueprint $table) {
            $table->dropForeign(['dine_bill_id']);
            $table->dropIndex(['dine_bill_id']);
            $table->dropColumn(['dine_bill_id', 'unit_cost', 'tax_type', 'tax_rate']);
        });
    }
};
