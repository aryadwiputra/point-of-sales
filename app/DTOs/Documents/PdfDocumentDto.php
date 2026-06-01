<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

use Barryvdh\DomPDF\PDF;

readonly class PdfDocumentDto
{
    public function __construct(
        public PDF $pdf,
        public string $filename
    ) {}
}
