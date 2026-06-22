<?php

declare(strict_types=1);

namespace App\Services\Products;

use App\Models\Product;
use App\Services\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateProductService
{
    public function __construct(
        private readonly ProductUnitSyncService $unitSync,
        private readonly ProductPayloadService $payloadService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(Product $product, array $data, ?UploadedFile $image): void
    {
        $before = $this->payloadService->auditPayload($product->load('units'));
        $baseUnit = collect($data['product_units'])->firstWhere('is_base_unit', true);

        DB::transaction(function () use ($product, $data, $baseUnit, $image) {
            $payload = [
                'barcode' => $baseUnit['barcode'],
                'sku' => $data['sku'],
                'title' => $data['title'],
                'description' => $data['description'],
                'category_id' => $data['category_id'],
                'buy_price' => $baseUnit['buy_price'],
                'sell_price' => $baseUnit['sell_price'],
            ];

            if ($image) {
                if ($product->image) {
                    Storage::disk('local')->delete('public/products/'.basename($product->image));
                }

                $image->storeAs('public/products', $image->hashName());
                $payload['image'] = $image->hashName();
            }

            $product->update($payload);
            $this->unitSync->sync($product, $data['product_units']);
        });

        $this->logProductUpdate($product, $before);
    }

    private function logProductUpdate(Product $product, array $before): void
    {
        $after = $this->payloadService->auditPayload($product->fresh(['units']));

        $this->auditLogService->log(
            event: 'product.updated',
            module: 'products',
            auditable: $product,
            description: 'Data produk diperbarui.',
            before: $before,
            after: $after
        );

        if (
            (int) $before['buy_price'] !== (int) $after['buy_price']
            || (int) $before['sell_price'] !== (int) $after['sell_price']
        ) {
            $this->auditLogService->log(
                event: 'product.price_updated',
                module: 'products',
                auditable: $product,
                description: 'Harga produk diperbarui.',
                before: [
                    'buy_price' => $before['buy_price'],
                    'sell_price' => $before['sell_price'],
                ],
                after: [
                    'buy_price' => $after['buy_price'],
                    'sell_price' => $after['sell_price'],
                ]
            );
        }
    }
}
