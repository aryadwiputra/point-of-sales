<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Documents\PdfDocumentDto;
use App\Models\Payable;
use App\Models\Receivable;
use App\Services\Documents\GeneratePdfDocumentService;
use Illuminate\Http\Response;

class DocumentController extends Controller
{
    public function invoice(string $invoice, GeneratePdfDocumentService $service): Response
    {
        return $this->stream($service->invoice($invoice));
    }

    public function publicInvoice(string $invoice, GeneratePdfDocumentService $service): Response
    {
        return $this->stream($service->invoice($invoice));
    }

    public function receipt(string $invoice, GeneratePdfDocumentService $service, string $size = '80'): Response
    {
        return $this->stream($service->receipt($invoice, $size));
    }

    public function shipping(string $invoice, GeneratePdfDocumentService $service): Response
    {
        return $this->stream($service->shipping($invoice));
    }

    public function receivable(Receivable $receivable, GeneratePdfDocumentService $service): Response
    {
        return $this->stream($service->receivable($receivable));
    }

    public function payable(Payable $payable, GeneratePdfDocumentService $service): Response
    {
        return $this->stream($service->payable($payable));
    }

    private function stream(PdfDocumentDto $document): Response
    {
        return $document->pdf->stream($document->filename);
    }
}
