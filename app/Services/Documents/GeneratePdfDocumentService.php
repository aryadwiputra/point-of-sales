<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\DTOs\Documents\PdfDocumentDto;
use App\Models\Payable;
use App\Models\Receivable;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Picqer\Barcode\BarcodeGeneratorPNG;

class GeneratePdfDocumentService
{
    public function __construct(
        private readonly DocumentPayloadQueryService $payloads
    ) {}

    public function invoice(string $invoice): PdfDocumentDto
    {
        $transaction = $this->payloads->transaction($invoice);
        $document = $this->load('pdf.invoice', [
            'transaction' => $transaction,
            'barcode' => $this->barcode($transaction->invoice),
        ], "invoice-{$transaction->invoice}.pdf");

        $document->pdf->setPaper('a4');

        return $document;
    }

    public function receipt(string $invoice, string $size = '80'): PdfDocumentDto
    {
        $transaction = $this->payloads->transaction($invoice);
        $size = $size === '58' ? '58' : '80';
        $width = $size === '58' ? 164.4 : 226.8;
        $document = $this->load("pdf.receipt_{$size}", [
            'transaction' => $transaction,
            'barcode' => $this->barcode($transaction->invoice),
        ], "receipt-{$transaction->invoice}-{$size}.pdf");

        $document->pdf->setPaper([0, 0, $width, 800], 'portrait');

        return $document;
    }

    public function shipping(string $invoice): PdfDocumentDto
    {
        $transaction = $this->payloads->transaction($invoice);
        $document = $this->load('pdf.shipping_label', [
            'transaction' => $transaction,
            'barcode' => $this->barcode($transaction->invoice),
        ], "shipping-{$transaction->invoice}.pdf");

        $document->pdf->setPaper([0, 0, 425, 283], 'landscape');

        return $document;
    }

    public function receivable(Receivable $receivable): PdfDocumentDto
    {
        $receivable = $this->payloads->receivable($receivable);
        $document = $this->load('pdf.receivable', [
            'receivable' => $receivable,
            'barcode' => $this->barcode($receivable->invoice),
        ], "piutang-{$receivable->invoice}.pdf");

        $document->pdf->setPaper('a5', 'portrait');

        return $document;
    }

    public function payable(Payable $payable): PdfDocumentDto
    {
        $payable = $this->payloads->payable($payable);
        $document = $this->load('pdf.payable', [
            'payable' => $payable,
            'barcode' => $this->barcode($payable->document_number),
        ], "hutang-{$payable->document_number}.pdf");

        $document->pdf->setPaper('a5', 'portrait');

        return $document;
    }

    private function load(string $view, array $payload, string $filename): PdfDocumentDto
    {
        File::ensureDirectoryExists(storage_path('fonts'));

        return new PdfDocumentDto(
            pdf: Pdf::loadView($view, [
                ...$payload,
                'store' => $this->payloads->storeProfile(),
            ]),
            filename: $filename,
        );
    }

    private function barcode(string $code): string
    {
        $generator = new BarcodeGeneratorPNG;
        $data = $generator->getBarcode($code, $generator::TYPE_CODE_128);

        return 'data:image/png;base64,'.base64_encode($data);
    }
}
