<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Services\OutletAccessService;
use App\Services\ThermalPrintService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Picqer\Barcode\BarcodeGeneratorPNG;

class DocumentController extends Controller
{
    public function __construct(
        private readonly OutletAccessService $outletAccessService,
    ) {}

    private function ensureWarehouseAccess(?Warehouse $warehouse): void
    {
        abort_unless($this->outletAccessService->canUseWarehouse(request()->user(), $warehouse), 404);
    }

    private function ensureFontDirectory(): void
    {
        $fontDir = storage_path('fonts');
        if (! is_dir($fontDir)) {
            @mkdir($fontDir, 0755, true);
        }
    }

    private function storeProfile(?Outlet $outlet = null): array
    {
        $logo = Setting::getForOutlet('store_logo', $outlet);
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
            'name' => Setting::getForOutlet('store_name', $outlet, 'Toko Anda'),
            'logo' => $logo,
            'logo_data' => $logoData,
            'address' => Setting::getForOutlet('store_address', $outlet, ''),
            'phone' => Setting::getForOutlet('store_phone', $outlet, ''),
            'email' => Setting::getForOutlet('store_email', $outlet, ''),
            'website' => Setting::getForOutlet('store_website', $outlet, ''),
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

        $transaction = Transaction::with(['details.product', 'cashier', 'customer', 'warehouse.outlet'])
            ->where('invoice', $invoice)
            ->firstOrFail();
        $this->ensureWarehouseAccess($transaction->warehouse);

        $pdf = Pdf::loadView('pdf.invoice', [
            'transaction' => $transaction,
            'store' => $this->storeProfile($transaction->warehouse?->outlet),
            'barcode' => $this->barcode($transaction->invoice),
        ])->setPaper('a4');

        return $pdf->stream("invoice-{$transaction->invoice}.pdf");
    }

    /**
     * Public version of invoice (no auth needed, but requires the transaction access token).
     */
    public function publicInvoice(string $invoice, Request $request)
    {
        $this->ensureFontDirectory();

        $transaction = Transaction::with(['details.product', 'cashier', 'customer', 'warehouse.outlet'])
            ->where('invoice', $invoice)
            ->where('access_token', $request->query('token'))
            ->firstOrFail();

        $pdf = Pdf::loadView('pdf.invoice', [
            'transaction' => $transaction,
            'store' => $this->storeProfile($transaction->warehouse?->outlet),
            'barcode' => $this->barcode($transaction->invoice),
        ])->setPaper('a4');

        return $pdf->stream("invoice-{$transaction->invoice}.pdf");
    }

    public function receipt(string $invoice, string $size = '80')
    {
        $this->ensureFontDirectory();

        $transaction = Transaction::with(['details.product', 'cashier', 'customer', 'warehouse.outlet'])
            ->where('invoice', $invoice)
            ->firstOrFail();
        $this->ensureWarehouseAccess($transaction->warehouse);

        $template = $size === '58' ? 'pdf.receipt_58' : 'pdf.receipt_80';
        $width = $size === '58' ? 164.4 : 226.8; // points (mm*2.8346)
        $pdf = Pdf::loadView($template, [
            'transaction' => $transaction,
            'store' => $this->storeProfile($transaction->warehouse?->outlet),
            'barcode' => $this->barcode($transaction->invoice),
        ])->setPaper([0, 0, $width, 800], 'portrait');

        return $pdf->stream("receipt-{$transaction->invoice}-{$size}.pdf");
    }

    public function shipping(string $invoice)
    {
        $this->ensureFontDirectory();

        $transaction = Transaction::with(['details.product', 'customer', 'cashier', 'warehouse.outlet'])
            ->where('invoice', $invoice)
            ->firstOrFail();
        $this->ensureWarehouseAccess($transaction->warehouse);

        $pdf = Pdf::loadView('pdf.shipping_label', [
            'transaction' => $transaction,
            'store' => $this->storeProfile($transaction->warehouse?->outlet),
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
        $this->ensureWarehouseAccess($transaction->warehouse);

        $service = app(ThermalPrintService::class);
        $html = $service->generateReceiptHtml($transaction);

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function receivable(Receivable $receivable)
    {
        $this->ensureFontDirectory();

        $receivable->load(['customer', 'payments.bankAccount', 'payments.user', 'transaction.warehouse.outlet']);
        $this->ensureWarehouseAccess($receivable->transaction?->warehouse);

        $pdf = Pdf::loadView('pdf.receivable', [
            'receivable' => $receivable,
            'store' => $this->storeProfile($receivable->transaction?->warehouse?->outlet),
            'barcode' => $this->barcode($receivable->invoice),
        ])->setPaper('a5', 'portrait');

        return $pdf->stream("piutang-{$receivable->invoice}.pdf");
    }

    public function payable(Payable $payable)
    {
        $this->ensureFontDirectory();

        $payable->load(['supplier', 'payments.bankAccount', 'payments.user', 'purchaseOrder.warehouse.outlet']);
        $this->ensureWarehouseAccess($payable->purchaseOrder?->warehouse);

        $pdf = Pdf::loadView('pdf.payable', [
            'payable' => $payable,
            'store' => $this->storeProfile($payable->purchaseOrder?->warehouse?->outlet),
            'barcode' => $this->barcode($payable->document_number),
        ])->setPaper('a5', 'portrait');

        return $pdf->stream("hutang-{$payable->document_number}.pdf");
    }
}
