<?php

namespace App\Http\Controllers;

use App\Models\Payable;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Services\ThermalPrintService;
use Barryvdh\DomPDF\Facade\Pdf;
use Picqer\Barcode\BarcodeGeneratorPNG;

class DocumentController extends Controller
{
    private function ensureFontDirectory(): void
    {
        $fontDir = storage_path('fonts');
        if (! is_dir($fontDir)) {
            @mkdir($fontDir, 0755, true);
        }
    }

    private function storeProfile(): array
    {
        $logo = \App\Models\Setting::get('store_logo');
        if ($logo && ! str_starts_with($logo, 'http') && ! str_starts_with($logo, '/storage')) {
            $logo = asset('storage/'.ltrim($logo, '/'));
        }

        $logoData = null;
        if ($logo) {
            $localPath = null;
            if (str_starts_with($logo, asset('storage'))) {
                $localPath = public_path(str_replace(asset(''), '', $logo));
            } elseif (str_starts_with($logo, '/storage')) {
                $localPath = public_path($logo);
            }

            if ($localPath && file_exists($localPath)) {
                $logoData = 'data:image/png;base64,'.base64_encode(file_get_contents($localPath));
            }
        }

        return [
            'name' => \App\Models\Setting::get('store_name', 'Toko Anda'),
            'logo' => $logo,
            'logo_data' => $logoData,
            'address' => \App\Models\Setting::get('store_address', ''),
            'phone' => \App\Models\Setting::get('store_phone', ''),
            'email' => \App\Models\Setting::get('store_email', ''),
            'website' => \App\Models\Setting::get('store_website', ''),
        ];
    }

    private function barcode(string $code): string
    {
        $generator = new BarcodeGeneratorPNG;
        $data = $generator->getBarcode($code, $generator::TYPE_CODE_128);

        return 'data:image/png;base64,'.base64_encode($data);
    }

    public function invoice(string $invoice)
    {
        $this->ensureFontDirectory();

        $transaction = Transaction::with(['details.product', 'cashier', 'customer'])
            ->where('invoice', $invoice)
            ->firstOrFail();

        $pdf = Pdf::loadView('pdf.invoice', [
            'transaction' => $transaction,
            'store' => $this->storeProfile(),
            'barcode' => $this->barcode($transaction->invoice),
        ])->setPaper('a4');

        return $pdf->stream("invoice-{$transaction->invoice}.pdf");
    }

    /**
     * Public version of invoice (no auth needed).
     */
    public function publicInvoice(string $invoice)
    {
        return $this->invoice($invoice);
    }

    public function receipt(string $invoice, string $size = '80')
    {
        $this->ensureFontDirectory();

        $transaction = Transaction::with(['details.product', 'cashier', 'customer'])
            ->where('invoice', $invoice)
            ->firstOrFail();

        $template = $size === '58' ? 'pdf.receipt_58' : 'pdf.receipt_80';
        $width = $size === '58' ? 164.4 : 226.8; // points (mm*2.8346)
        $pdf = Pdf::loadView($template, [
            'transaction' => $transaction,
            'store' => $this->storeProfile(),
            'barcode' => $this->barcode($transaction->invoice),
        ])->setPaper([0, 0, $width, 800], 'portrait');

        return $pdf->stream("receipt-{$transaction->invoice}-{$size}.pdf");
    }

    public function shipping(string $invoice)
    {
        $this->ensureFontDirectory();

        $transaction = Transaction::with(['details.product', 'customer', 'cashier'])
            ->where('invoice', $invoice)
            ->firstOrFail();

        $pdf = Pdf::loadView('pdf.shipping_label', [
            'transaction' => $transaction,
            'store' => $this->storeProfile(),
            'barcode' => $this->barcode($transaction->invoice),
        ]);

        // Set kertas 150mm x 100mm (dalam Points: 1mm = 2.83465pt)
        // 150mm = 425pt, 100mm = 283pt
        $pdf->setPaper([0, 0, 425, 283], 'landscape');

        return $pdf->stream("shipping-{$transaction->invoice}.pdf");
    }

    public function thermalPrint(string $invoice)
    {
        $transaction = Transaction::with(['details.product', 'cashier', 'customer'])
            ->where('invoice', $invoice)
            ->firstOrFail();

        $service = app(ThermalPrintService::class);
        $html = $service->generateReceiptHtml($transaction);

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function receivable(Receivable $receivable)
    {
        $this->ensureFontDirectory();

        $receivable->load(['customer', 'payments.bankAccount', 'payments.user']);

        $pdf = Pdf::loadView('pdf.receivable', [
            'receivable' => $receivable,
            'store' => $this->storeProfile(),
            'barcode' => $this->barcode($receivable->invoice),
        ])->setPaper('a5', 'portrait');

        return $pdf->stream("piutang-{$receivable->invoice}.pdf");
    }

    public function payable(Payable $payable)
    {
        $this->ensureFontDirectory();

        $payable->load(['supplier', 'payments.bankAccount', 'payments.user']);

        $pdf = Pdf::loadView('pdf.payable', [
            'payable' => $payable,
            'store' => $this->storeProfile(),
            'barcode' => $this->barcode($payable->document_number),
        ])->setPaper('a5', 'portrait');

        return $pdf->stream("hutang-{$payable->document_number}.pdf");
    }
}
