<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('product_unit_id')->nullable()->after('product_id')->constrained('product_units')->nullOnDelete();
            $table->string('unit_label')->nullable()->after('product_unit_id');
            $table->decimal('unit_conversion_qty', 10, 3)->default(1)->after('unit_label');

            $table->index(['cashier_id', 'product_id', 'product_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex(['cashier_id', 'product_id', 'product_unit_id']);
            $table->dropForeign(['product_unit_id']);
            $table->dropColumn([
                'product_unit_id',
                'unit_label',
                'unit_conversion_qty',
            ]);
        });
    }
};
