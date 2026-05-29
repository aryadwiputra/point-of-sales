<?php

declare(strict_types=1);

namespace App\Services\Products;

use App\Models\Product;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Storage;

class DeleteProductService
{
    public function __construct(
        private readonly ProductPayloadService $payloadService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(Product $product): void
    {
        $before = $this->payloadService->auditPayload($product);

        if ($product->image) {
            Storage::disk('local')->delete('public/products/'.basename($product->image));
        }

        $product->delete();

        $this->auditLogService->log(
            event: 'product.deleted',
            module: 'products',
            auditable: $product,
            description: 'Produk dihapus.',
            before: $before
        );
    }
}
