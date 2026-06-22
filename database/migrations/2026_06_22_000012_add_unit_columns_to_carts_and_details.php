<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained('units');
            $table->decimal('conversion_factor', 15, 4)->default(1.0000);
        });

        Schema::table('transaction_details', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained('units');
            $table->decimal('conversion_factor', 15, 4)->default(1.0000);
        });
    }

    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'conversion_factor']);
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'conversion_factor']);
        });
    }
};
