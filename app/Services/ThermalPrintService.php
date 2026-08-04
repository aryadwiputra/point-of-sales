<?php

namespace App\Services;

use App\Models\Setting;
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

        if ($transaction->payment_method === 'cash' && $transaction->cash > 0) {
            $lines[] = $this->leftRight('Tunai', number_format((int) $transaction->cash, 0, ',', '.'), $maxWidth);
            if (($transaction->change ?? 0) > 0) {
                $lines[] = $this->leftRight('Kembali', number_format((int) $transaction->change, 0, ',', '.'), $maxWidth);
            }
        }

        $lines[] = $this->line($maxWidth);
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
