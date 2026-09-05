<?php

namespace Tests\Feature\Transactions;

use App\Models\Cart;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CartAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->cashierA = User::where('email', 'cashier@gmail.com')->first();
        $this->cashierA->markEmailAsVerified();

        $this->cashierB = User::factory()->create();
        $this->cashierB->markEmailAsVerified();
        Permission::findByName('transactions-access')->assignRole('cashier');
        $this->cashierB->assignRole('cashier');

        $this->actingAs($this->cashierA);
    }

    private function makeProduct(): Product
    {
        $category = Category::create([
            'name' => 'Sembako',
            'description' => 'Kategori pengujian',
            'image' => 'category.png',
        ]);

        return Product::create([
            'category_id' => $category->id,
            'image' => 'product.png',
            'barcode' => 'BRCD-'.Str::upper(Str::random(10)),
            'title' => 'Produk Uji',
            'description' => 'Deskripsi produk uji.',
            'buy_price' => 45000,
            'sell_price' => 60000,
            'stock' => 25,
            'tax_rate' => 0,
        ]);
    }

    private function openShiftFor(User $user): CashierShift
    {
        return CashierShift::create([
            'user_id' => $user->id,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'opening_cash' => 100000,
            'expected_cash' => 100000,
            'status' => 'open',
        ]);
    }

    public function test_cashier_cannot_delete_another_cashiers_cart(): void
    {
        $this->openShiftFor($this->cashierA);
        $this->openShiftFor($this->cashierB);

        $product = $this->makeProduct();
        $otherCart = Cart::create([
            'cashier_id' => $this->cashierB->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => $product->sell_price,
        ]);

        $this->actingAs($this->cashierA)
            ->from(route('transactions.index'))
            ->delete(route('transactions.destroyCart', $otherCart->id))
            ->assertSessionHasErrors('message');

        $this->assertDatabaseHas('carts', ['id' => $otherCart->id]);
    }

    public function test_cashier_can_delete_own_cart(): void
    {
        $this->openShiftFor($this->cashierA);

        $product = $this->makeProduct();
        $ownCart = Cart::create([
            'cashier_id' => $this->cashierA->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => $product->sell_price,
        ]);

        $this->actingAs($this->cashierA)
            ->from(route('transactions.index'))
            ->delete(route('transactions.destroyCart', $ownCart->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('carts', ['id' => $ownCart->id]);
    }
}
