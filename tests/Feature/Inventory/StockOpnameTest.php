<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'stock-opnames-access',
            'stock-opnames-create',
            'stock-opnames-finalize',
            'stock-mutations-access',
            'products-create',
            'products-edit',
        ] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_authorized_user_can_create_stock_opname_draft(): void
    {
        $user = $this->createUserWithPermissions([
            'stock-opnames-access',
            'stock-opnames-create',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('stock-opnames.store'), [
                'notes' => 'Opname bulanan gudang depan',
            ]);

        $stockOpname = StockOpname::first();

        $response->assertRedirect(route('stock-opnames.show', $stockOpname));
        $this->assertNotNull($stockOpname);
        $this->assertSame('draft', $stockOpname->status);
        $this->assertSame('Opname bulanan gudang depan', $stockOpname->notes);
        $this->assertSame($user->id, $stockOpname->created_by);
        $this->assertStringStartsWith('SO-', $stockOpname->code);
    }

    public function test_duplicate_product_cannot_be_added_twice_to_same_stock_opname(): void
    {
        $user = $this->createUserWithPermissions([
            'stock-opnames-access',
            'stock-opnames-create',
        ]);
        $product = $this->createProduct(18);
        $stockOpname = StockOpname::create([
            'code' => 'SO-TEST-001',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('stock-opnames.items.store', $stockOpname), [
            'product_id' => $product->id,
        ]);

        $response = $this
            ->from(route('stock-opnames.show', $stockOpname))
            ->actingAs($user)
            ->post(route('stock-opnames.items.store', $stockOpname), [
                'product_id' => $product->id,
            ]);

        $response->assertInvalid(['product_id']);
        $this->assertDatabaseCount('stock_opname_items', 1);
    }

    public function test_updating_stock_opname_item_calculates_difference(): void
    {
        $user = $this->createUserWithPermissions([
            'stock-opnames-access',
            'stock-opnames-create',
        ]);
        $product = $this->createProduct(10);
        $stockOpname = StockOpname::create([
            'code' => 'SO-TEST-002',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $item = StockOpnameItem::create([
            'stock_opname_id' => $stockOpname->id,
            'product_id' => $product->id,
            'system_stock' => $product->stock,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('stock-opnames.items.update', [$stockOpname, $item]), [
                'physical_stock' => 7,
                'adjustment_reason' => 'Barang rusak',
            ]);

        $response->assertSessionDoesntHaveErrors();
        $item->refresh();

        $this->assertSame(7, $item->physical_stock);
        $this->assertSame(-3, $item->difference);
        $this->assertSame('Barang rusak', $item->adjustment_reason);
    }

    public function test_finalize_updates_product_stock_and_creates_mutation(): void
    {
        $user = $this->createUserWithPermissions([
            'stock-opnames-access',
            'stock-opnames-create',
            'stock-opnames-finalize',
        ]);
        $product = $this->createProduct(12);
        $stockOpname = StockOpname::create([
            'code' => 'SO-TEST-003',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $stockOpname->id,
            'product_id' => $product->id,
            'system_stock' => $product->stock,
            'physical_stock' => 8,
            'difference' => -4,
            'adjustment_reason' => 'Selisih hitung fisik',
        ]);

        $response = $this
            ->from(route('stock-opnames.show', $stockOpname))
            ->actingAs($user)
            ->post(route('stock-opnames.finalize', $stockOpname));

        $response->assertRedirect(route('stock-opnames.show', $stockOpname));

        $this->assertSame(8, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_opnames', [
            'id' => $stockOpname->id,
            'status' => 'finalized',
            'finalized_by' => $user->id,
        ]);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'reference_type' => 'stock_opname',
            'reference_id' => $stockOpname->id,
            'mutation_type' => 'adjustment',
            'qty' => 4,
            'stock_before' => 12,
            'stock_after' => 8,
        ]);
    }

    public function test_finalize_rejects_difference_without_reason(): void
    {
        $user = $this->createUserWithPermissions([
            'stock-opnames-access',
            'stock-opnames-create',
            'stock-opnames-finalize',
        ]);
        $product = $this->createProduct(12);
        $stockOpname = StockOpname::create([
            'code' => 'SO-TEST-004',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $stockOpname->id,
            'product_id' => $product->id,
            'system_stock' => $product->stock,
            'physical_stock' => 10,
            'difference' => -2,
            'adjustment_reason' => null,
        ]);

        $response = $this
            ->from(route('stock-opnames.show', $stockOpname))
            ->actingAs($user)
            ->post(route('stock-opnames.finalize', $stockOpname));

        $response->assertInvalid(['finalize']);
        $this->assertSame(12, $product->fresh()->stock);
        $this->assertDatabaseMissing('stock_mutations', [
            'reference_type' => 'stock_opname',
            'reference_id' => $stockOpname->id,
        ]);
    }

    public function test_finalized_session_cannot_be_updated(): void
    {
        $user = $this->createUserWithPermissions([
            'stock-opnames-access',
            'stock-opnames-create',
        ]);
        $product = $this->createProduct(9);
        $stockOpname = StockOpname::create([
            'code' => 'SO-TEST-005',
            'status' => 'finalized',
            'created_by' => $user->id,
            'finalized_by' => $user->id,
            'finalized_at' => now(),
        ]);
        $item = StockOpnameItem::create([
            'stock_opname_id' => $stockOpname->id,
            'product_id' => $product->id,
            'system_stock' => $product->stock,
        ]);

        $response = $this
            ->from(route('stock-opnames.show', $stockOpname))
            ->actingAs($user)
            ->patch(route('stock-opnames.items.update', [$stockOpname, $item]), [
                'physical_stock' => 7,
            ]);

        $response->assertInvalid(['stock_opname']);
        $this->assertNull($item->fresh()->physical_stock);
    }

    public function test_product_update_does_not_change_stock_directly(): void
    {
        $user = $this->createUserWithPermissions(['products-edit']);
        $product = $this->createProduct(20);

        $response = $this
            ->actingAs($user)
            ->put(route('products.update', $product), [
                'barcode' => $product->barcode,
                'sku' => $product->sku,
                'title' => 'Produk Revisi',
                'description' => $product->description,
                'category_id' => $product->category_id,
                'buy_price' => $product->buy_price,
                'sell_price' => $product->sell_price,
                'stock' => 999,
            ]);

        $response->assertRedirect(route('products.index'));
        $product->refresh();

        $this->assertSame('Produk Revisi', $product->title);
        $this->assertSame(20, $product->stock);
    }

    public function test_product_create_generates_initial_stock_mutation(): void
    {
        Storage::fake('local');

        $user = $this->createUserWithPermissions(['products-create']);
        $category = Category::create([
            'name' => 'Minuman',
            'description' => 'Kategori minuman',
            'image' => 'minuman.png',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('products.store'), [
                'image' => UploadedFile::fake()->image('product.png'),
                'barcode' => 'BRCD-'.Str::upper(Str::random(8)),
                'sku' => 'SKU-'.Str::upper(Str::random(8)),
                'title' => 'Produk Baru',
                'description' => 'Deskripsi produk baru',
                'category_id' => $category->id,
                'buy_price' => 10000,
                'sell_price' => 15000,
                'stock' => 15,
            ]);

        $product = Product::latest('id')->first();

        $response->assertRedirect(route('products.index'));
        $this->assertNotNull($product);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'reference_type' => 'product_create',
            'reference_id' => $product->id,
            'mutation_type' => 'in',
            'qty' => 15,
            'stock_before' => 0,
            'stock_after' => 15,
            'created_by' => $user->id,
        ]);
    }

    private function createUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function createProduct(int $stock = 10): Product
    {
        $category = Category::create([
            'name' => 'Kategori '.Str::upper(Str::random(5)),
            'description' => 'Kategori pengujian',
            'image' => 'category.png',
        ]);

        return Product::create([
            'category_id' => $category->id,
            'image' => 'product.png',
            'barcode' => 'BRCD-'.Str::upper(Str::random(10)),
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'title' => 'Produk Uji '.Str::upper(Str::random(4)),
            'description' => 'Deskripsi produk uji.',
            'buy_price' => 45000,
            'sell_price' => 60000,
            'stock' => $stock,
            'tax_rate' => 0,
        ]);
    }
}
