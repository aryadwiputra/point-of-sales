<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Models\Cart;

class DeleteCartItemService
{
    public function execute(int|string $cartId, int $userId): array
    {
        $cart = Cart::with('product')
            ->whereId($cartId)
            ->where('cashier_id', $userId)
            ->active()
            ->first();

        if (! $cart) {
            return [
                'success' => false,
                'message' => 'Cart not found',
                'status' => 404,
            ];
        }

        $cart->delete();

        return [
            'success' => true,
            'message' => 'Item dihapus dari keranjang',
            'status' => 200,
        ];
    }
}
