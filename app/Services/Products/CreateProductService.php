<?php

declare(strict_types=1);

namespace App\Services\Products;

use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\StockMutationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateProductService
{
    public function __construct(
        private readonly ProductUnitSyncService $unitSync,
        private readonly ProductPayloadService $payloadService,
        private readonly StockMutationService $stockMutationService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(array $data, ?UploadedFile $image, ?int $userId): Product
    {
        $baseUnit = collect($data['product_units'])->firstWhere('is_base_unit', true);

        $product = DB::transaction(function () use ($data, $image, $baseUnit, $userId) {
            $imageName = null;

            if ($image) {
                $image->storeAs('public/products', $image->hashName());
                $imageName = $image->hashName();
            }

            $product = Product::create([
                'image' => $imageName,
                'barcode' => $baseUnit['barcode'],
                'sku' => $data['sku'],
                'title' => $data['title'],
                'description' => $data['description'],
                'category_id' => $data['category_id'],
                'buy_price' => $baseUnit['buy_price'],
                'sell_price' => $baseUnit['sell_price'],
                'stock' => $data['stock'],
            ]);

            $this->unitSync->sync($product, $data['product_units']);
            $this->stockMutationService->recordInitialStock($product, $userId);

            return $product;
        });

        $this->auditLogService->log(
            event: 'product.created',
            module: 'products',
            auditable: $product,
            description: 'Produk baru dibuat.',
            after: $this->payloadService->auditPayload($product->fresh(['units']))
        );

        return $product;
    }
}
