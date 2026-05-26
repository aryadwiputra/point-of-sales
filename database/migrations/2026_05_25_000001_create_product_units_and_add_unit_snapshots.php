<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('conversion_qty', 10, 3);
            $table->boolean('is_base_unit')->default(false);
            $table->bigInteger('sell_price');
            $table->bigInteger('buy_price');
            $table->string('barcode')->unique();
            $table->timestamps();

            $table->index('product_id');
        });

        $this->createBaseUnitConstraint();

        Schema::table('transaction_details', function (Blueprint $table) {
            $table->decimal('qty', 10, 3)->change();
            $table->foreignId('product_unit_id')->nullable()->after('product_id')->constrained('product_units')->nullOnDelete();
            $table->string('unit_label')->nullable()->after('product_unit_id');
            $table->decimal('unit_conversion_qty', 10, 3)->default(1)->after('unit_label');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->decimal('qty', 10, 3)->change();
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->decimal('qty_sold', 10, 3)->change();
            $table->decimal('qty_returned_before', 10, 3)->default(0)->change();
            $table->decimal('qty_return', 10, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->integer('qty_sold')->change();
            $table->integer('qty_returned_before')->default(0)->change();
            $table->integer('qty_return')->change();
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->integer('qty')->change();
        });

        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropForeign(['product_unit_id']);
            $table->dropColumn([
                'product_unit_id',
                'unit_label',
                'unit_conversion_qty',
            ]);
            $table->integer('qty')->change();
        });

        Schema::dropIfExists('product_units');
    }

    private function createBaseUnitConstraint(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(
                'CREATE UNIQUE INDEX product_units_one_base_unit_per_product ON product_units (product_id) WHERE is_base_unit = 1'
            );

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX product_units_one_base_unit_per_product ON product_units (product_id) WHERE is_base_unit = true'
            );

            return;
        }

        if ($driver === 'mysql') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER product_units_one_base_unit_before_insert
                BEFORE INSERT ON product_units
                FOR EACH ROW
                BEGIN
                    IF NEW.is_base_unit = 1
                        AND EXISTS (
                            SELECT 1
                            FROM product_units
                            WHERE product_id = NEW.product_id
                                AND is_base_unit = 1
                        )
                    THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Only one base unit is allowed per product';
                    END IF;
                END
                SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER product_units_one_base_unit_before_update
                BEFORE UPDATE ON product_units
                FOR EACH ROW
                BEGIN
                    IF NEW.is_base_unit = 1
                        AND EXISTS (
                            SELECT 1
                            FROM product_units
                            WHERE product_id = NEW.product_id
                                AND is_base_unit = 1
                                AND id <> NEW.id
                        )
                    THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Only one base unit is allowed per product';
                    END IF;
                END
                SQL);

            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX product_units_one_base_unit_per_product ON product_units ((CASE WHEN is_base_unit = 1 THEN product_id ELSE NULL END))'
        );
    }
};
