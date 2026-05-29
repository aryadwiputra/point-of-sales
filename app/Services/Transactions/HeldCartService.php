<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Models\Cart;

class HeldCartService
{
    public function hold(int $userId, ?string $label): array
    {
        $activeCarts = Cart::where('cashier_id', $userId)
            ->active()
            ->get();

        if ($activeCarts->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Keranjang kosong, tidak ada yang bisa ditahan',
                'status' => 422,
            ];
        }

        $label = $label ?: 'Transaksi '.now()->format('H:i');

        Cart::where('cashier_id', $userId)
            ->active()
            ->update([
                'hold_id' => 'HOLD-'.strtoupper(uniqid()),
                'hold_label' => $label,
                'held_at' => now(),
            ]);

        return [
            'success' => true,
            'message' => 'Transaksi ditahan: '.$label,
            'status' => 200,
        ];
    }

    public function resume(int $userId, string $holdId): array
    {
        $activeCarts = Cart::where('cashier_id', $userId)
            ->active()
            ->count();

        if ($activeCarts > 0) {
            return [
                'success' => false,
                'message' => 'Selesaikan atau tahan transaksi aktif terlebih dahulu',
                'status' => 422,
            ];
        }

        $heldCarts = Cart::where('cashier_id', $userId)
            ->forHold($holdId)
            ->get();

        if ($heldCarts->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Transaksi ditahan tidak ditemukan',
                'status' => 404,
            ];
        }

        Cart::where('cashier_id', $userId)
            ->forHold($holdId)
            ->update([
                'hold_id' => null,
                'hold_label' => null,
                'held_at' => null,
            ]);

        return [
            'success' => true,
            'message' => 'Transaksi dilanjutkan',
            'status' => 200,
        ];
    }

    public function clear(int $userId, string $holdId): array
    {
        $deleted = Cart::where('cashier_id', $userId)
            ->forHold($holdId)
            ->delete();

        if ($deleted === 0) {
            return [
                'success' => false,
                'message' => 'Transaksi ditahan tidak ditemukan',
                'status' => 404,
            ];
        }

        return [
            'success' => true,
            'message' => 'Transaksi ditahan berhasil dihapus',
            'status' => 200,
        ];
    }

    public function list(int $userId): array
    {
        $heldCarts = Cart::with(['product:id,title,sell_price,image', 'productUnit:id,label,sell_price'])
            ->where('cashier_id', $userId)
            ->held()
            ->get()
            ->groupBy('hold_id')
            ->map(function ($items, $holdId) {
                $first = $items->first();

                return [
                    'hold_id' => $holdId,
                    'label' => $first->hold_label,
                    'held_at' => $first->held_at,
                    'items_count' => $items->sum('qty'),
                    'total' => $items->sum('price'),
                    'items' => $items->map(fn ($item) => [
                        'id' => $item->id,
                        'product' => $item->product,
                        'product_unit' => $item->productUnit,
                        'unit_label' => $item->unit_label,
                        'unit_conversion_qty' => $item->unit_conversion_qty,
                        'qty' => $item->qty,
                        'price' => $item->price,
                    ]),
                ];
            })
            ->values();

        return [
            'success' => true,
            'held_carts' => $heldCarts,
        ];
    }
}
