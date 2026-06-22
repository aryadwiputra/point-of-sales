<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Models\Cart;

class UpdateCartItemService
{
    public function execute(int|string $cartId, float $qty, int $userId): array
    {
        $cart = Cart::with(['product', 'productUnit'])
            ->whereId($cartId)
            ->where('cashier_id', $userId)
            ->first();

        if (! $cart) {
            return [
                'success' => false,
                'message' => 'Cart item not found',
                'status' => 404,
            ];
        }

        $baseQty = $qty * (float) ($cart->unit_conversion_qty ?: 1);

        if ((float) $cart->product->stock < $baseQty) {
            return [
                'success' => false,
                'message' => 'Stok tidak mencukupi. Tersedia: '.$cart->product->stock,
                'status' => 422,
            ];
        }

        $cart->qty = $qty;
        $cart->price = (int) round(($cart->productUnit?->sell_price ?? $cart->product->sell_price) * $qty);
        $cart->save();

        return [
            'success' => true,
            'message' => 'Quantity updated successfully',
            'status' => 200,
        ];
    }
}
