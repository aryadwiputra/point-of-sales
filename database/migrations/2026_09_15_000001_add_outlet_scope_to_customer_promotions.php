<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['customer_vouchers', 'customer_campaigns'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'outlet_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('outlet_id')->nullable()->after('id')->constrained('outlets')->nullOnDelete();
                    $table->index('outlet_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['customer_vouchers', 'customer_campaigns'] as $tableName) {
            if (Schema::hasColumn($tableName, 'outlet_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['outlet_id']);
                    $table->dropIndex(['outlet_id']);
                    $table->dropColumn('outlet_id');
                });
            }
        }
    }
};
