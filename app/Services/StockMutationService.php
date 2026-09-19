<?php

namespace App\Services;

use App\Models\GoodsReceiving;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\SupplierReturn;

class StockMutationService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function recordMutation(
        Product $product,
        ?int $warehouseId,
        string $referenceType,
        ?int $referenceId,
        string $mutationType,
        int $qty,
        int $stockBefore,
        int $stockAfter,
        ?string $notes = null,
        ?int $userId = null
    ): StockMutation {
        return StockMutation::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'mutation_type' => $mutationType,
            'qty' => $qty,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'notes' => $notes,
            'created_by' => $userId,
        ]);
    }

    public function recordInitialStock(Product $product, ?int $userId = null, ?int $warehouseId = null): ?StockMutation
    {
        $initialStock = (int) $product->stock;

        if ($initialStock <= 0) {
            return null;
        }

        $mutation = $this->recordMutation(
            product: $product,
            warehouseId: $warehouseId,
            referenceType: 'product_create',
            referenceId: $product->id,
            mutationType: 'in',
            qty: $initialStock,
            stockBefore: 0,
            stockAfter: $initialStock,
            notes: 'Initial stock saat produk dibuat.',
            userId: $userId,
        );

        $this->auditLogService->log(
            event: 'stock.adjusted',
            module: 'stock',
            auditable: $product,
            description: 'Initial stock produk dicatat.',
            before: [
                'product_id' => $product->id,
                'stock_before' => 0,
                'stock_after' => 0,
                'difference' => 0,
                'reason' => 'Initial stock saat produk dibuat.',
                'reference' => 'product:'.$product->id,
            ],
            after: [
                'product_id' => $product->id,
                'stock_before' => 0,
                'stock_after' => $initialStock,
                'difference' => $initialStock,
                'reason' => 'Initial stock saat produk dibuat.',
                'reference' => 'product:'.$product->id,
            ],
            meta: [
                'stock_mutation_id' => $mutation->id,
                'reference_type' => $mutation->reference_type,
                'reference_id' => $mutation->reference_id,
                'mutation_type' => $mutation->mutation_type,
                'qty' => (int) $mutation->qty,
            ],
        );

        return $mutation;
    }

    public function recordStockOpnameAdjustment(
        Product $product,
        StockOpname $stockOpname,
        int $stockBefore,
        int $stockAfter,
        ?string $reason,
        ?int $userId = null
    ): ?StockMutation {
        if ($stockBefore === $stockAfter) {
            return null;
        }

        $mutation = $this->recordMutation(
            product: $product,
            warehouseId: $stockOpname->warehouse_id,
            referenceType: 'stock_opname',
            referenceId: $stockOpname->id,
            mutationType: 'adjustment',
            qty: abs($stockAfter - $stockBefore),
            stockBefore: $stockBefore,
            stockAfter: $stockAfter,
            notes: $reason ?: 'Adjustment dari stock opname.',
            userId: $userId,
        );

        $this->auditLogService->log(
            event: 'stock.adjusted',
            module: 'stock',
            auditable: $product,
            description: 'Stok produk disesuaikan melalui stock opname.',
            before: [
                'product_id' => $product->id,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore,
                'difference' => 0,
                'reason' => $reason,
                'reference' => $stockOpname->code,
            ],
            after: [
                'product_id' => $product->id,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'difference' => $stockAfter - $stockBefore,
                'reason' => $reason ?: 'Adjustment dari stock opname.',
                'reference' => $stockOpname->code,
            ],
            meta: [
                'stock_mutation_id' => $mutation->id,
                'stock_opname_id' => $stockOpname->id,
                'stock_opname_code' => $stockOpname->code,
                'mutation_type' => $mutation->mutation_type,
                'qty' => (int) $mutation->qty,
            ],
        );

        return $mutation;
    }

    public function recordSalesReturnRestock(
        Product $product,
        SalesReturn $salesReturn,
        int $stockBefore,
        int $stockAfter,
        ?string $reason,
        ?int $userId = null
    ): ?StockMutation {
        if ($stockBefore === $stockAfter) {
            return null;
        }

        $mutation = $this->recordMutation(
            product: $product,
            warehouseId: $salesReturn->warehouse_id ?: $salesReturn->transaction?->warehouse_id,
            referenceType: 'sales_return',
            referenceId: $salesReturn->id,
            mutationType: 'in',
            qty: abs($stockAfter - $stockBefore),
            stockBefore: $stockBefore,
            stockAfter: $stockAfter,
            notes: $reason ?: 'Restock dari retur penjualan.',
            userId: $userId,
        );

        $this->auditLogService->log(
            event: 'stock.adjusted',
            module: 'stock',
            auditable: $product,
            description: 'Stok produk bertambah dari restock retur penjualan.',
            before: [
                'product_id' => $product->id,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore,
                'difference' => 0,
                'reason' => $reason,
                'reference' => $salesReturn->code,
            ],
            after: [
                'product_id' => $product->id,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'difference' => $stockAfter - $stockBefore,
                'reason' => $reason ?: 'Restock dari retur penjualan.',
                'reference' => $salesReturn->code,
            ],
            meta: [
                'stock_mutation_id' => $mutation->id,
                'sales_return_id' => $salesReturn->id,
                'sales_return_code' => $salesReturn->code,
                'mutation_type' => $mutation->mutation_type,
                'qty' => (int) $mutation->qty,
            ],
        );

        return $mutation;
    }

    public function recordPurchaseInbound(
        Product $product,
        GoodsReceiving $goodsReceiving,
        int $qty,
        int $stockBefore,
        int $stockAfter,
        ?string $notes = null,
        ?int $userId = null
    ): StockMutation {
        $mutation = $this->recordMutation(
            product: $product,
            warehouseId: $goodsReceiving->warehouse_id,
            referenceType: 'goods_receiving',
            referenceId: $goodsReceiving->id,
            mutationType: 'in',
            qty: $qty,
            stockBefore: $stockBefore,
            stockAfter: $stockAfter,
            notes: $notes ?: 'Stok masuk dari penerimaan barang.',
            userId: $userId,
        );

        $this->auditLogService->log(
            event: 'stock.adjusted',
            module: 'stock',
            auditable: $product,
            description: 'Stok masuk dari penerimaan barang '.$goodsReceiving->document_number,
            before: [
                'product_id' => $product->id,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore,
                'difference' => 0,
                'reference' => $goodsReceiving->document_number,
            ],
            after: [
                'product_id' => $product->id,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'difference' => $stockAfter - $stockBefore,
                'reference' => $goodsReceiving->document_number,
            ],
            meta: [
                'stock_mutation_id' => $mutation->id,
                'goods_receiving_id' => $goodsReceiving->id,
                'document_number' => $goodsReceiving->document_number,
                'mutation_type' => $mutation->mutation_type,
                'qty' => $qty,
            ],
        );

        return $mutation;
    }

    public function recordSupplierReturnOut(
        Product $product,
        SupplierReturn $supplierReturn,
        int $qty,
        int $stockBefore,
        int $stockAfter,
        ?string $notes = null,
        ?int $userId = null
    ): StockMutation {
        $mutation = $this->recordMutation(
            product: $product,
            warehouseId: $supplierReturn->warehouse_id,
            referenceType: 'supplier_return',
            referenceId: $supplierReturn->id,
            mutationType: 'out',
            qty: $qty,
            stockBefore: $stockBefore,
            stockAfter: $stockAfter,
            notes: $notes ?: 'Retur barang ke supplier.',
            userId: $userId,
        );

        $this->auditLogService->log(
            event: 'stock.adjusted',
            module: 'stock',
            auditable: $product,
            description: 'Stok keluar dari retur supplier '.$supplierReturn->document_number,
            before: [
                'product_id' => $product->id,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore,
                'difference' => 0,
                'reference' => $supplierReturn->document_number,
            ],
            after: [
                'product_id' => $product->id,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'difference' => $stockAfter - $stockBefore,
                'reference' => $supplierReturn->document_number,
            ],
            meta: [
                'stock_mutation_id' => $mutation->id,
                'supplier_return_id' => $supplierReturn->id,
                'document_number' => $supplierReturn->document_number,
                'mutation_type' => $mutation->mutation_type,
                'qty' => $qty,
            ],
        );

        return $mutation;
    }
}
