<?php

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SupplierReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SupplierReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'supplier-returns-access',
            'supplier-returns-create',
            'supplier-returns-update',
        ] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_complete_supplier_return_decrements_stock_when_sufficient(): void
    {
        [$user, $product, $warehouse] = $this->fixtures(stock: 10, pivotStock: 10);

        $return = $this->draftReturn($user, $product, $warehouse, qtyReturned: 4);

        $this->actingAs($user)
            ->post(route('supplier-returns.complete', $return))
            ->assertRedirect(route('supplier-returns.show', $return));

        $this->assertSame(6, $product->fresh()->stock);
        $this->assertDatabaseHas('product_warehouse', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 6,
        ]);
        $this->assertDatabaseHas('supplier_returns', [
            'id' => $return->id,
            'status' => 'completed',
        ]);
    }

    public function test_complete_supplier_return_rejects_when_stock_insufficient(): void
    {
        [$user, $product, $warehouse] = $this->fixtures(stock: 2, pivotStock: 2);

        $return = $this->draftReturn($user, $product, $warehouse, qtyReturned: 5);

        $this->expectException(ValidationException::class);

        try {
            app(SupplierReturnService::class)->complete($return, $user->id);
        } finally {
            // No mutation must survive a rejected completion.
            $this->assertSame(2, $product->fresh()->stock);
            $this->assertDatabaseHas('product_warehouse', [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'stock' => 2,
            ]);
            $this->assertDatabaseHas('supplier_returns', [
                'id' => $return->id,
                'status' => 'draft',
            ]);
            $this->assertDatabaseCount('stock_mutations', 0);
        }
    }

    private function fixtures(int $stock, int $pivotStock): array
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            'supplier-returns-access',
            'supplier-returns-create',
            'supplier-returns-update',
        ]);

        $warehouse = Warehouse::create([
            'name' => 'PUSAT',
            'code' => 'PUSAT',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        $category = Category::create([
            'name' => 'Kategori '.Str::upper(Str::random(5)),
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'image' => 'product.png',
            'barcode' => 'BRCD-'.Str::upper(Str::random(10)),
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'title' => 'Produk Supplier Return',
            'description' => 'Deskripsi',
            'buy_price' => 1000,
            'sell_price' => 2000,
            'stock' => $stock,
            'tax_rate' => 0,
        ]);

        $warehouse->products()->attach($product->id, ['stock' => $pivotStock]);

        return [$user, $product, $warehouse];
    }

    private function draftReturn(User $user, Product $product, Warehouse $warehouse, int $qtyReturned): SupplierReturn
    {
        $return = SupplierReturn::create([
            'warehouse_id' => $warehouse->id,
            'document_number' => 'SR-TEST-'.Str::upper(Str::random(6)),
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $return->items()->create([
            'product_id' => $product->id,
            'qty_returned' => $qtyReturned,
            'unit_price' => 1000,
            'reason' => 'Rusak',
        ]);

        return $return;
    }
}
