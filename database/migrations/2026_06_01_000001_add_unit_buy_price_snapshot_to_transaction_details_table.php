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
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->bigInteger('unit_buy_price')->nullable()->after('unit_price');
        });

        $productBuyPrices = DB::table('products')->pluck('buy_price', 'id');
        $productUnitBuyPrices = DB::table('product_units')->pluck('buy_price', 'id');

        DB::table('transaction_details')
            ->select('id', 'product_id', 'product_unit_id', 'unit_conversion_qty')
            ->orderBy('id')
            ->chunkById(500, function ($details) use ($productBuyPrices, $productUnitBuyPrices) {
                foreach ($details as $detail) {
                    $unitBuyPrice = $detail->product_unit_id
                        ? $productUnitBuyPrices->get($detail->product_unit_id)
                        : null;

                    if ($unitBuyPrice === null) {
                        $baseBuyPrice = (int) ($productBuyPrices->get($detail->product_id) ?? 0);
                        $conversionQty = (float) ($detail->unit_conversion_qty ?: 1);
                        $unitBuyPrice = (int) round($baseBuyPrice * $conversionQty);
                    }

                    DB::table('transaction_details')
                        ->where('id', $detail->id)
                        ->update(['unit_buy_price' => $unitBuyPrice]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropColumn('unit_buy_price');
        });
    }
};
