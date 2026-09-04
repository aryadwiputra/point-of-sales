<?php

namespace Tests\Feature\Products;

use App\Models\Cart;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompositeProductTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Warehouse $pusat;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->admin = User::where('email', 'arya@gmail.com')->first();
        $this->admin->markEmailAsVerified();

        $this->pusat = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->category = Category::create([
            'name' => 'Paket Uji',
            'description' => 'Kategori pengujian',
            'image' => 'category.png',
        ]);
    }

    protected function createComponent(string $title, int $sellPrice = 40000, int $stock = 25): Product
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'image' => 'product.png',
            'barcode' => 'BRCD-'.Str::upper(Str::random(10)),
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'title' => $title,
            'description' => 'Komponen uji.',
            'buy_price' => 20000,
            'sell_price' => $sellPrice,
            'stock' => $stock,
            'tax_rate' => 0,
        ]);

        $this->pusat->products()->attach($product->id, ['stock' => $stock]);

        return $product;
    }

    protected function createComposite(array $components): Product
    {
        $composite = Product::create([
            'category_id' => $this->category->id,
            'image' => 'product.png',
            'barcode' => 'BRCD-'.Str::upper(Str::random(10)),
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'title' => 'Paket Hemat',
            'description' => 'Produk komposit uji.',
            'buy_price' => 0,
            'sell_price' => 0,
            'stock' => 0,
            'is_composite' => true,
            'tax_rate' => 0,
        ]);

        $composite->components()->attach($components);

        return $composite;
    }

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'image' => UploadedFile::fake()->image('product.png'),
            'barcode' => 'BRCD-'.Str::upper(Str::random(10)),
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'title' => 'Paket Hemat',
            'description' => 'Produk komposit uji.',
            'category_id' => $this->category->id,
            'buy_price' => 0,
            'sell_price' => 0,
            'stock' => 0,
            'is_composite' => '1',
            'tax_rate' => 0,
        ], $overrides);
    }

    public function test_store_creates_composite_with_components(): void
    {
        $component = $this->createComponent('Komponen A');

        $this->actingAs($this->admin)
            ->post(route('products.store'), $this->validPayload([
                'components' => [
                    ['component_product_id' => $component->id, 'qty' => 3],
                ],
            ]))
            ->assertRedirect(route('products.index'));

        $composite = Product::where('title', 'Paket Hemat')->firstOrFail();

        $this->assertTrue($composite->is_composite);
        $this->assertDatabaseHas('composite_product_items', [
            'composite_product_id' => $composite->id,
            'component_product_id' => $component->id,
            'qty' => 3,
        ]);
    }

    public function test_store_rejects_composite_without_components(): void
    {
        $this->actingAs($this->admin)
            ->from(route('products.create'))
            ->post(route('products.store'), $this->validPayload())
            ->assertSessionHasErrors('components');
    }

    public function test_store_rejects_composite_component(): void
    {
        $composite = $this->createComposite([
            $this->createComponent('Komponen A')->id => ['qty' => 1],
        ]);

        $this->actingAs($this->admin)
            ->post(route('products.store'), $this->validPayload([
                'title' => 'Paket Bertingkat',
                'components' => [
                    ['component_product_id' => $composite->id, 'qty' => 1],
                ],
            ]))
            ->assertStatus(422);
    }

    public function test_update_rejects_self_referencing_component(): void
    {
        $component = $this->createComponent('Komponen A');
        $composite = $this->createComposite([$component->id => ['qty' => 1]]);

        $this->actingAs($this->admin)
            ->put(route('products.update', $composite), [
                'barcode' => $composite->barcode,
                'sku' => $composite->sku,
                'title' => $composite->title,
                'description' => $composite->description,
                'category_id' => $this->category->id,
                'buy_price' => 0,
                'sell_price' => 0,
                'is_composite' => '1',
                'components' => [
                    ['component_product_id' => $composite->id, 'qty' => 1],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_update_can_convert_product_to_composite(): void
    {
        $component = $this->createComponent('Komponen A');

        $product = Product::create([
            'category_id' => $this->category->id,
            'image' => 'product.png',
            'barcode' => 'BRCD-'.Str::upper(Str::random(10)),
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'title' => 'Produk Biasa',
            'description' => 'Produk biasa.',
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 10,
            'tax_rate' => 0,
        ]);

        $this->actingAs($this->admin)
            ->put(route('products.update', $product), [
                'barcode' => $product->barcode,
                'sku' => $product->sku,
                'title' => 'Produk Biasa',
                'description' => 'Produk biasa.',
                'category_id' => $this->category->id,
                'buy_price' => 0,
                'sell_price' => 0,
                'is_composite' => '1',
                'components' => [
                    ['component_product_id' => $component->id, 'qty' => 2],
                ],
            ])
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('composite_product_items', [
            'composite_product_id' => $product->id,
            'component_product_id' => $component->id,
            'qty' => 2,
        ]);
        $this->assertTrue($product->fresh()->is_composite);
    }

    public function test_update_cannot_remove_all_components_from_composite(): void
    {
        $component = $this->createComponent('Komponen A');
        $composite = $this->createComposite([$component->id => ['qty' => 1]]);

        $this->actingAs($this->admin)
            ->from(route('products.edit', $composite))
            ->put(route('products.update', $composite), [
                'barcode' => $composite->barcode,
                'sku' => $composite->sku,
                'title' => $composite->title,
                'description' => $composite->description,
                'category_id' => $this->category->id,
                'buy_price' => 0,
                'sell_price' => 0,
                'is_composite' => '1',
                'components' => [],
            ])
            ->assertSessionHasErrors('components');
    }

    public function test_composite_checkout_decrements_component_stock(): void
    {
        $cashier = User::factory()->create();
        $cashier->givePermissionTo([
            'transactions-access',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
        ]);

        $shift = CashierShift::create([
            'user_id' => $cashier->id,
            'opened_by' => $cashier->id,
            'opened_at' => now(),
            'opening_cash' => 100000,
            'expected_cash' => 100000,
            'status' => 'open',
            'warehouse_id' => $this->pusat->id,
        ]);

        $componentA = $this->createComponent('Komponen A', 60000, 25);
        $componentB = $this->createComponent('Komponen B', 40000, 25);
        $composite = $this->createComposite([
            $componentA->id => ['qty' => 2],
            $componentB->id => ['qty' => 1],
        ]);

        $customer = Customer::create([
            'name' => 'Pembeli Paket',
            'no_telp' => 62812345,
            'address' => 'Jl. Uji No. 1',
        ]);

        // Add composite to cart via POS flow (price = sum of components)
        $this->actingAs($cashier)
            ->post(route('transactions.addToCart'), [
                'product_id' => $composite->id,
                'qty' => 1,
            ])
            ->assertRedirect(route('transactions.index'));

        $cart = Cart::where('cashier_id', $cashier->id)->where('product_id', $composite->id)->firstOrFail();
        $this->assertSame(160000, (int) $cart->price);

        $response = $this->actingAs($cashier)
            ->post(route('transactions.store'), [
                'customer_id' => $customer->id,
                'discount' => 0,
                'grand_total' => $cart->price,
                'cash' => 200000,
                'change' => 40000,
            ]);

        $transaction = Transaction::latest('id')->first();
        $response->assertRedirect(route('transactions.print', $transaction->invoice));

        $this->assertSame(1, $transaction->details->count());
        $this->assertSame($composite->id, $transaction->details->first()->product_id);
        $this->assertSame(160000, (int) $transaction->details->first()->price);

        // Component stock decremented (global + warehouse pivot)
        $this->assertSame(23, $componentA->fresh()->stock);
        $this->assertSame(24, $componentB->fresh()->stock);
        $this->assertSame(23, (int) $componentA->fresh()->warehouses()->where('warehouse_id', $this->pusat->id)->first()->pivot->stock);
        $this->assertSame(24, (int) $componentB->fresh()->warehouses()->where('warehouse_id', $this->pusat->id)->first()->pivot->stock);

        // Composite itself has no stock of its own
        $this->assertSame(0, $composite->fresh()->stock);
    }
}
