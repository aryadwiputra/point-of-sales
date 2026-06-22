@php
    $line = str_repeat('=', 32);
    $dash = str_repeat('-', 32);
    $formatPrice = fn($v) => 'Rp ' . number_format($v ?? 0, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        body { font-family: 'Inter','Helvetica','Arial',sans-serif; width: 58mm; margin: 0; padding: 6px; font-size: 11px; line-height: 1.4; }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .barcode img { height: 24px; }
        .section { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="center section" style="margin-top:0;">
        <div class="bold" style="margin-bottom:2px;">{{ $store['name'] }}</div>
        @if($store['address'])<div>{{ $store['address'] }}</div>@endif
        @if($store['phone'])<div>Telp: {{ $store['phone'] }}</div>@endif
        @if($store['email'])<div>Email: {{ $store['email'] }}</div>@endif
        @if($store['website'])<div>{{ $store['website'] }}</div>@endif
    </div>

    <pre style="margin:3px 0;">{{ $line }}</pre>

    <div class="section">
        <div style="display:flex; justify-content:space-between;">
            <span>No:</span>
            <span>{{ $transaction->invoice }}</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
            <span>Tgl:</span>
            <span>{{ \Carbon\Carbon::parse($transaction->created_at)->format('d/m/Y H:i') }}</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
            <span>Kasir:</span>
            <span>{{ $transaction->cashier->name ?? '-' }}</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
            <span>Pelanggan:</span>
            <span>{{ $transaction->customer->name ?? 'Umum' }}</span>
        </div>
    </div>

    <pre style="margin:3px 0;">{{ $line }}</pre>

    <div class="section">
        @foreach($transaction->details as $item)
            @php
                $qty = max(1, $item->qty);
                $total = $item->price;
                $unit = $item->unit_price ?: ($qty ? $total / $qty : $total);
            @endphp
            <div style="font-weight:600;">{{ $item->product->title ?? 'Produk' }}</div>
            @if($item->discount_total > 0 && ($item->pricing_group_label || $item->pricing_rule_name))
                <div style="display:flex; justify-content:space-between; font-size:9px; color:#64748b;">
                    <span>Promo: {{ $item->pricing_group_label ?: $item->pricing_rule_name }}</span>
                    <span>{{ $formatPrice($item->base_unit_price) }}</span>
                </div>
            @endif
            <div style="display:flex; justify-content:space-between;">
                <span>{{ $qty }}x @ {{ $formatPrice($unit) }}</span>
                <span>{{ $formatPrice($total) }}</span>
            </div>
        @endforeach
    </div>

    <pre style="margin:3px 0;">{{ $dash }}</pre>

    @php
        $promoDiscount = $transaction->details->sum('discount_total');
        $voucherDiscount = $transaction->customer_voucher_discount ?? 0;
        $loyaltyDiscount = $transaction->loyalty_discount_total ?? 0;
        $subtotal = ($transaction->grand_total ?? 0) + ($transaction->discount ?? 0) - ($transaction->shipping_cost ?? 0) - ($transaction->tax_total ?? 0) + $promoDiscount + $voucherDiscount + $loyaltyDiscount;
        $discount = $transaction->discount ?? 0;
        $total = $transaction->grand_total ?? 0;
        $shipping = $transaction->shipping_cost ?? 0;
        $taxTotal = $transaction->tax_total ?? 0;
        $taxRate = $transaction->tax_rate ?? 0;
        $cash = $transaction->cash ?? 0;
        $change = $transaction->change ?? 0;
        $paymentMethod = strtoupper($transaction->payment_method ?? 'TUNAI');
    @endphp

    <div class="section">
        <div style="display:flex; justify-content:space-between;">
            <span>Subtotal</span>
            <span>{{ $formatPrice($subtotal) }}</span>
        </div>
        @if($promoDiscount > 0)
            <div style="display:flex; justify-content:space-between;">
                <span>Promo</span>
                <span>-{{ $formatPrice($promoDiscount) }}</span>
            </div>
        @endif
        @if($discount > 0)
            <div style="display:flex; justify-content:space-between;">
                <span>Diskon Manual</span>
                <span>-{{ $formatPrice($discount) }}</span>
            </div>
        @endif
        @if($voucherDiscount > 0)
            <div style="display:flex; justify-content:space-between;">
                <span>Voucher</span>
                <span>-{{ $formatPrice($voucherDiscount) }}</span>
            </div>
        @endif
        @if($loyaltyDiscount > 0)
            <div style="display:flex; justify-content:space-between;">
                <span>Redeem Poin</span>
                <span>-{{ $formatPrice($loyaltyDiscount) }}</span>
            </div>
        @endif
        @if($shipping > 0)
            <div style="display:flex; justify-content:space-between;">
                <span>Ongkir</span>
                <span>{{ $formatPrice($shipping) }}</span>
            </div>
        @endif
        @if($taxTotal > 0)
            <div style="display:flex; justify-content:space-between;">
                <span>PPN {{ number_format($taxRate, 0) }}%</span>
                <span>{{ $formatPrice($taxTotal) }}</span>
            </div>
        @endif
        <div style="display:flex; justify-content:space-between; font-weight:700; font-size:12px;">
            <span>TOTAL</span>
            <span>{{ $formatPrice($total) }}</span>
        </div>
    </div>

    <pre style="margin:3px 0;">{{ $dash }}</pre>

    <div class="section">
        <div style="display:flex; justify-content:space-between;">
            <span>Bayar ({{ $paymentMethod }})</span>
            <span>{{ $formatPrice($cash) }}</span>
        </div>
        @if($change > 0)
            <div style="display:flex; justify-content:space-between; font-weight:700;">
                <span>Kembali</span>
                <span>{{ $formatPrice($change) }}</span>
            </div>
        @endif
    </div>

    <pre style="margin:3px 0;">{{ $line }}</pre>

    <div class="center section" style="margin-bottom:0;">
        <div class="barcode">
            <img src="{{ $barcode }}" alt="barcode">
        </div>
        <div style="font-size:10px;">{{ $transaction->invoice }}</div>
        <div>Terima kasih!</div>
    </div>
</body>
</html>
