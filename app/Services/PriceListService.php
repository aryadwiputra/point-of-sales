<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;

class PriceListService
{
    // ponytail: N+1 per product; eager-load items via priceList->items when pricing whole carts
    public function getApplicablePriceList(?Customer $customer): ?PriceList
    {
        $lists = PriceList::active()->orderBy('priority', 'desc')->get();

        foreach ($lists as $list) {
            if ($list->customer_scope === 'all') {
                return $list;
            }
            if ($list->customer_scope === 'walk_in') {
                return $list;
            }
            if ($list->customer_scope === 'registered' && $customer) {
                return $list;
            }
            if ($list->customer_scope === 'member' && $customer?->is_loyalty_member) {
                return $list;
            }
            if ($list->customer_scope === 'segment' && $customer && $list->customer_segment_id) {
                if ($customer->segments()->where('customer_segment_id', $list->customer_segment_id)->exists()) {
                    return $list;
                }
            }
        }

        return null;
    }

    public function getProductPrice(PriceList $priceList, int $productId): ?int
    {
        return $priceList->items()->where('product_id', $productId)->value('price');
    }

    public function getBasePrice(Product $product, ?Customer $customer): int
    {
        if ($product->is_composite) {
            return (int) $product->components->sum(
                fn ($c) => (int) $c->sell_price * (float) $c->pivot->qty
            );
        }

        $priceList = $this->getApplicablePriceList($customer);
        if (! $priceList) {
            return (int) $product->sell_price;
        }

        return (int) ($this->getProductPrice($priceList, $product->id) ?? $product->sell_price);
    }
}
