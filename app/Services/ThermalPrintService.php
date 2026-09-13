<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\Setting;
use App\Models\ShiftCashMovement;
use App\Models\Transaction;

class ThermalPrintService
{
    public function generateReceiptText(Transaction $transaction, string $paperSize = '80mm'): string
    {
        $storeName = Setting::get('store_name', 'Toko Anda');
        $storeAddress = Setting::get('store_address', '');
        $storePhone = Setting::get('store_phone', '');
        $maxWidth = $paperSize === '58mm' ? 32 : 48;

        $lines = [];
        $lines[] = '';
        $lines[] = $this->center(strtoupper($storeName ?? 'TOKO ANDA'), $maxWidth);
        if ($storeAddress) {
            $lines[] = $this->center($storeAddress, $maxWidth);
        }
        if ($storePhone) {
            $lines[] = $this->center('Telp: '.$storePhone, $maxWidth);
        }
        $lines[] = $this->line($maxWidth);
        $lines[] = $this->left('No: '.($transaction->invoice ?? ''), $maxWidth);
        $lines[] = $this->left('Tgl: '.($transaction->created_at?->format('d/m/Y H:i') ?? ''), $maxWidth);
        $lines[] = $this->left('Kasir: '.($transaction->cashier?->name ?? '-'), $maxWidth);
        $lines[] = $this->left('Pelanggan: '.($transaction->customer?->name ?? 'Umum'), $maxWidth);
        if ($transaction->order_type) {
            $labels = ['in_store' => 'Di Tempat', 'takeaway' => 'Bawa Pulang', 'delivery' => 'Diantar'];
            $lines[] = $this->left('Tipe: '.($labels[$transaction->order_type] ?? $transaction->order_type), $maxWidth);
        }
        $lines[] = $this->line($maxWidth);

        foreach ($transaction->details as $detail) {
            $title = mb_substr($detail->product?->title ?? 'Produk', 0, $maxWidth - 10);
            $linePrice = number_format((int) $detail->price, 0, ',', '.');
            $lineTotal = "{$detail->qty}x @ ".number_format((int) ($detail->unit_price ?: $detail->price / max(1, $detail->qty)), 0, ',', '.');
            $lines[] = $this->left($title, $maxWidth);
            $lines[] = $this->leftRight($lineTotal, $linePrice, $maxWidth);
        }

        $lines[] = $this->line($maxWidth);
        $subtotal = ($transaction->grand_total ?? 0) + ($transaction->discount ?? 0) - ($transaction->shipping_cost ?? 0) - ($transaction->tax_total ?? 0);
        $lines[] = $this->leftRight('Subtotal', number_format($subtotal, 0, ',', '.'), $maxWidth);
        if (($transaction->discount ?? 0) > 0) {
            $lines[] = $this->leftRight('Diskon', '-'.number_format((int) $transaction->discount, 0, ',', '.'), $maxWidth);
        }
        if (($transaction->tax_total ?? 0) > 0) {
            $lines[] = $this->leftRight('PPN', number_format((int) $transaction->tax_total, 0, ',', '.'), $maxWidth);
        }
        if (($transaction->shipping_cost ?? 0) > 0) {
            $lines[] = $this->leftRight('Ongkir', number_format((int) $transaction->shipping_cost, 0, ',', '.'), $maxWidth);
        }
        $lines[] = $this->line($maxWidth);
        $lines[] = $this->leftRight('TOTAL', number_format((int) $transaction->grand_total, 0, ',', '.'), $maxWidth);

        $tenders = $transaction->relationLoaded('tenders')
            ? $transaction->tenders
            : $transaction->tenders()->get();

        if ($tenders->isNotEmpty()) {
            foreach ($tenders as $tender) {
                $label = match ($tender->method) {
                    'cash' => 'Tunai',
                    'bank_transfer' => 'Transfer Bank',
                    'midtrans' => 'Midtrans',
                    'xendit' => 'Xendit',
                    'qris' => 'QRIS',
                    default => ucfirst(str_replace('_', ' ', $tender->method)),
                };
                $lines[] = $this->leftRight($label, number_format((int) $tender->amount, 0, ',', '.'), $maxWidth);
                if ($tender->method === 'cash' && $tender->change > 0) {
                    $lines[] = $this->leftRight('Kembali', number_format((int) $tender->change, 0, ',', '.'), $maxWidth);
                }
            }
        } elseif ($transaction->payment_method === 'cash' && $transaction->cash > 0) {
            $lines[] = $this->leftRight('Tunai', number_format((int) $transaction->cash, 0, ',', '.'), $maxWidth);
            if (($transaction->change ?? 0) > 0) {
                $lines[] = $this->leftRight('Kembali', number_format((int) $transaction->change, 0, ',', '.'), $maxWidth);
            }
        }

        $lines[] = $this->line($maxWidth);
        if ($transaction->note) {
            foreach (explode("\n", wordwrap($transaction->note, $maxWidth, "\n", true)) as $noteLine) {
                $lines[] = $this->left('Cat: '.$noteLine, $maxWidth);
            }
        }
        $lines[] = $this->center('Terima kasih', $maxWidth);
        $lines[] = $this->center('Barang yang sudah dibeli', $maxWidth);
        $lines[] = $this->center('tidak dapat ditukar/dikembalikan', $maxWidth);
        $lines[] = '';
        $lines[] = '';

        return implode("\n", $lines);
    }

    public function generateReceiptHtml(Transaction $transaction): string
    {
        $text = $this->generateReceiptText($transaction, '80mm');

        return '<pre style="font-family:monospace;font-size:12px;line-height:1.4;width:80mm;margin:0;padding:4mm;">'.e($text).'</pre>';
    }

    public function generateShiftReportText(CashierShift $shift, string $paperSize = '80mm', string $reportType = 'X'): string
    {
        $storeName = Setting::get('store_name', 'Toko Anda');
        $storeAddress = Setting::get('store_address', '');
        $maxWidth = $paperSize === '58mm' ? 32 : 48;
        $isX = $reportType === 'X';

        $lines = [];
        $lines[] = '';
        $lines[] = $this->center(strtoupper($storeName ?? 'TOKO ANDA'), $maxWidth);
        if ($storeAddress) {
            $lines[] = $this->center($storeAddress, $maxWidth);
        }
        $lines[] = $this->line($maxWidth);
        $lines[] = $this->center("LAPORAN {$reportType} — SHIFT #{$shift->id}", $maxWidth);
        $lines[] = $this->line($maxWidth);
        $lines[] = $this->left('Kasir: '.($shift->user?->name ?? '-'), $maxWidth);
        $lines[] = $this->left('Gudang: '.($shift->warehouse?->name ?? '-'), $maxWidth);
        $lines[] = $this->left('Buka: '.($shift->opened_at?->format('d/m/Y H:i') ?? '-'), $maxWidth);

        if ($shift->closed_at) {
            $lines[] = $this->left('Tutup: '.$shift->closed_at->format('d/m/Y H:i'), $maxWidth);
        }

        $lines[] = $this->line($maxWidth);
        $lines[] = $this->leftRight('Penjualan Tunai', number_format((int) $shift->cash_sales_total, 0, ',', '.'), $maxWidth);
        $lines[] = $this->leftRight('Penjualan Non Tunai', number_format((int) $shift->non_cash_sales_total, 0, ',', '.'), $maxWidth);
        $lines[] = $this->leftRight('Retur Tunai', '-'.number_format((int) $shift->cash_refund_total, 0, ',', '.'), $maxWidth);
        $lines[] = $this->leftRight('Retur Non Tunai', '-'.number_format((int) $shift->non_cash_refund_total, 0, ',', '.'), $maxWidth);

        if ($shift->relationLoaded('cashMovements')) {
            $movements = $shift->cashMovements;
        } else {
            $movements = $shift->cashMovements()->get();
        }

        foreach ($movements as $movement) {
            $label = $movement->type === ShiftCashMovement::TYPE_IN ? 'Kas Masuk' : 'Kas Keluar';
            $lines[] = $this->leftRight($label, number_format((int) $movement->amount, 0, ',', '.'), $maxWidth);
            if ($movement->note) {
                $lines[] = $this->left('  ('.mb_substr($movement->note, 0, $maxWidth - 3).')', $maxWidth);
            }
        }

        $lines[] = $this->line($maxWidth);
        $lines[] = $this->leftRight('Total Transaksi', (string) $shift->transactions_count, $maxWidth);
        $lines[] = $this->leftRight('Total Retur', (string) $shift->sales_returns_count, $maxWidth);
        $lines[] = $this->line($maxWidth);
        $lines[] = $this->leftRight('MODAL AWAL', number_format((int) $shift->opening_cash, 0, ',', '.'), $maxWidth);
        $lines[] = $this->leftRight('EXPECTED CASH', number_format((int) $shift->expected_cash, 0, ',', '.'), $maxWidth);

        if ($shift->actual_cash !== null) {
            $lines[] = $this->leftRight('KAS FISIK', number_format((int) $shift->actual_cash, 0, ',', '.'), $maxWidth);
            $diffLabel = ($shift->cash_difference ?? 0) === 0
                ? 'SELISIH (BALANCE)'
                : 'SELISIH';
            $lines[] = $this->leftRight($diffLabel, number_format((int) $shift->cash_difference, 0, ',', '.'), $maxWidth);
        }

        $lines[] = $this->line($maxWidth);

        if ($shift->close_notes) {
            $lines[] = $this->left('Catatan: '.mb_substr($shift->close_notes, 0, $maxWidth - 9), $maxWidth);
        }

        $lines[] = $this->center($isX
            ? 'Laporan sementara — shift masih berjalan'
            : 'Laporan akhir penutupan shift',
            $maxWidth);
        $lines[] = $this->center('Dicetak '.now()->format('d/m/Y H:i'), $maxWidth);
        $lines[] = '';
        $lines[] = '';

        return implode("\n", $lines);
    }

    public function generateShiftReportHtml(CashierShift $shift, string $reportType = 'X'): string
    {
        $text = $this->generateShiftReportText($shift, '80mm', $reportType);

        return '<pre style="font-family:monospace;font-size:12px;line-height:1.4;width:80mm;margin:0;padding:4mm;">'.e($text).'</pre>';
    }

    private function center(string $text, int $width): string
    {
        $text = trim($text);
        $len = mb_strlen($text);
        if ($len >= $width) {
            return mb_substr($text, 0, $width);
        }
        $pad = (int) (($width - $len) / 2);

        return str_repeat(' ', max(0, $pad)).$text;
    }

    private function left(string $text, int $width): string
    {
        return mb_substr($text, 0, $width);
    }

    private function leftRight(string $left, string $right, int $width): string
    {
        $left = mb_substr($left, 0, $width - 15);
        $right = mb_substr($right, 0, 14);
        $dots = $width - mb_strlen($left) - mb_strlen($right);
        if ($dots < 1) {
            return $left.' '.$right;
        }

        return $left.str_repeat(' ', $dots).$right;
    }

    private function line(int $width): string
    {
        return str_repeat('-', $width);
    }
}
