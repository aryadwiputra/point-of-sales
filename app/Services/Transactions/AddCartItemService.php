<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Models\Cart;
use App\Models\Product;

class AddCartItemService
{
    public function __construct(
        private readonly ProductUnitResolverService $unitResolver
    ) {}

    public function execute(array $data, int $userId): array
    {
        $qty = (float) $data['qty'];
        $product = Product::with('units')->whereId($data['product_id'])->first();

        if (! $product) {
            return [
                'success' => false,
                'message' => 'Product not found.',
                'status' => 404,
            ];
        }

        $unit = $this->unitResolver->resolve($product, $data['product_unit_id'] ?? null);
        $baseQty = $qty * (float) $unit->conversion_qty;

        if ((float) $product->stock < $baseQty) {
            return [
                'success' => false,
                'message' => 'Stok tidak mencukupi. Tersedia: '.$product->stock,
                'status' => 422,
                'web_message' => 'Out of Stock Product!.',
            ];
        }

        $cart = Cart::with(['product', 'productUnit'])
            ->where('product_id', $data['product_id'])
            ->where('product_unit_id', $unit->id)
            ->where('cashier_id', $userId)
            ->active()
            ->first();

        if ($cart) {
            $newQty = (float) $cart->qty + $qty;
            $newBaseQty = $newQty * (float) $cart->unit_conversion_qty;

            if ((float) $product->stock < $newBaseQty) {
                return [
                    'success' => false,
                    'message' => 'Stok tidak mencukupi. Tersedia: '.$product->stock,
                    'status' => 422,
                    'web_message' => 'Out of Stock Product!.',
                ];
            }

            $cart->qty = $newQty;
            $cart->price = (int) round($unit->sell_price * $newQty);
            $cart->save();
        } else {
            Cart::create([
                'cashier_id' => $userId,
                'product_id' => $data['product_id'],
                'product_unit_id' => $unit->id,
                'unit_label' => $unit->label,
                'unit_conversion_qty' => $unit->conversion_qty,
                'qty' => $qty,
                'price' => (int) round($unit->sell_price * $qty),
            ]);
        }

        return [
            'success' => true,
            'message' => 'Product Added Successfully!.',
            'status' => 200,
        ];
    }
}
