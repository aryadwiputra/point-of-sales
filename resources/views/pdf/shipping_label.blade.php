@php
    $formatPrice = fn($v) => 'Rp ' . number_format($v ?? 0, 0, ',', '.');
    $formatDate = fn($v) => \Carbon\Carbon::parse($v)->format('d M Y');
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <style>
        /* Ukuran 150mm x 100mm dalam Points */
        @page {
            margin: 0;
            size: 425.2pt 283.5pt;
        }

        body {
            font-family: 'Helvetica', sans-serif;
            margin: 0;
            padding: 0;
            width: 425.2pt;
            height: 283.5pt;
            color: #1e293b;
        }

        .container {
            padding: 12pt;
            position: relative;
            height: 259pt;
            box-sizing: border-box;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td {
            vertical-align: top;
            overflow: hidden;
        }

        .header {
            width: 100%;
            table-layout: auto !important;
            /* Memungkinkan kolom logo merapat ke teks */
            margin-bottom: 5pt;
        }

        .header td {
            vertical-align: middle;
            padding: 0 4pt;
        }

        .logo-box {
            display: block;
            margin: 0;
            padding: 0;
        }

        .divider {
            border-top: 1px solid #e2e8f0;
            margin: 8pt 0;
        }

        .section-box {
            border: 1px solid #e2e8f0;
            border-radius: 6pt;
            padding: 6pt;
            height: 65pt;
            /* Tinggi tetap agar tidak mendorong footer */
        }

        .title-label {
            font-size: 7pt;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
            margin-bottom: 3pt;
        }

        .text-bold {
            font-size: 10pt;
            font-weight: bold;
        }

        .text-small {
            font-size: 8pt;
            line-height: 1.2;
        }

        .text-muted {
            color: #64748b;
            font-size: 7pt;
        }

        /* Footer dipaksa berada di bawah */
        .footer-absolute {
            position: absolute;
            bottom: 15pt;
            left: 15pt;
            right: 15pt;
            border-top: 1px solid #e2e8f0;
            padding-top: 8pt;
        }

        /* Container barcode agar ukurannya konsisten */
        .barcode-container {
            text-align: right;
            width: 220pt;
            /* Batasi lebar area barcode */
            float: right;
        }

        .barcode-img {
            height: 35pt;
            /* Sedikit lebih besar agar mudah di-scan */
            width: 100%;
            /* Mengikuti lebar container */
            max-width: 220pt;
            display: block;
            margin-left: auto;
            /* Aliran ke kanan */
        }

        .invoice-number {
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 2pt;
            margin-top: 5pt;
            color: #000;
            text-align: center;
            /* Nomor invoice rata tengah terhadap barcode */
        }

        ul {
            margin: 0;
            padding-left: 12pt;
        }

        li {
            font-size: 8pt;
            margin-bottom: 2pt;
        }
    </style>
</head>

<body>
    <div class="container">
        <table class="header" style="table-layout: auto;">
            <tr>
                <td style="width: 1%; padding-right: 8pt;">
                    <div class="logo-box" style="width: 40pt; height: 40pt;">
                        @if ($store['logo_data'] ?? false)
                            <img src="{{ $store['logo_data'] }}"
                                style="max-width: 40pt; max-height: 40pt; object-fit: contain;">
                        @else
                            <div
                                style="width: 40pt; height: 40pt; border: 1px solid #e2e8f0; line-height: 40pt; text-align: center; font-weight: bold; font-size: 17pt;">
                                {{ substr($store['name'], 0, 2) }}
                            </div>
                        @endif
                    </div>
                </td>
                <td style="text-align: left;">
                    <div class="text-bold" style="font-size: 13pt; line-height: 1.1;">{{ $store['name'] }} </div>
                    <div class="text-small text-muted" style="margin-top: 3pt;font-size: 7pt;">{{ Str::limit($store['address'], 60) }}
                    </div>
                    <div class="text-small text-muted" style="margin-top: 2pt;letter-spacing: 0.7pt;">
                        {{ $store['phone'] }}@if ($store['phone'] && $store['email'])
                            |
                        @endif{{ $store['email'] }}
                    </div>
                </td>
                <td width="180pt" style="text-align: right; vertical-align: top;">
                    <div class="text-muted" style="font-size: 7pt;">INVOICE </div>
                    <div class="text-bold" style="font-size: 15pt; color: #000; line-height: 1.1;">
                        {{ $transaction->invoice }}</div>
                    <div class="text-small">{{ $formatDate($transaction->created_at) }}</div>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <table>
            <tr>
                <td style="padding-right: 5pt; width: 65%;">
                    <div class="section-box">
                        <div class="title-label">Penerima</div>
                        <div class="text-bold">{{ $transaction->customer->name ?? 'Umum' }}</div>
                        <div class="text-small text-muted" style="margin-top: 5px; margin-bottom: 5px;">
                            {{ $transaction->customer->no_telp ?? '-' }}</div>
                        <div class="text-small text-muted">
                            {{ Str::limit($transaction->customer->address ?? 'No Address', 80) }}</div>
                        <div class="text-small text-muted" style="margin-top:2pt;">
                            {{ $transaction->customer->village_name ?? '-' }}
                            @if ($transaction->customer?->district_name)
                                , {{ $transaction->customer->district_name }}
                            @endif
                            @if ($transaction->customer?->regency_name)
                                , {{ $transaction->customer->regency_name }}
                            @endif
                            @if ($transaction->customer?->province_name)
                                , {{ $transaction->customer->province_name }}
                            @endif
                        </div>
                    </div>
                </td>
                <td style="padding-left: 5pt; width: 35%;">
                    <div class="section-box">
                        <div class="title-label">Ringkasan Pesanan</div>
                        <table class="text-small">
                            <tr>
                                <td>Item</td>
                                <td style="text-align: right;">{{ $transaction->details->count() }} unit</td>
                            </tr>
                            <tr>
                                <td style="padding-top: 15pt;" class="text-bold">Total</td>
                                <td style="padding-top: 15pt; text-align: right;" class="text-bold">
                                    {{ $formatPrice($transaction->grand_total) }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <div class="title-label" style="margin-top: 10pt;">Daftar Produk</div>
        <div style="height: 50pt; overflow: hidden;">
            <ul>
                @foreach ($transaction->details->take(3) as $detail)
                    <li>{{ Str::limit($detail->product->title, 40) }} ({{ $detail->qty }}x)</li>
                @endforeach
            </ul>
        </div>

        <div class="footer-absolute">
            <table style="table-layout: auto;">
                <tr>
                    <td class="text-muted" style="vertical-align: bottom; padding-bottom: 2pt;line-height: 1.5;">
                        Admin: <strong>{{ $transaction->cashier->name ?? '-' }}</strong><br>
                        Dicetak: {{ now()->format('d/m/Y H:i') }}
                    </td>

                    <td style="text-align: right; width: 150pt;">
                        <div class="barcode-container">
                            <img src="{{ $barcode }}" class="barcode-img" alt="barcode">
                            <div class="invoice-number">{{ $transaction->invoice }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>

</html>
