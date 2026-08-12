<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate([
            'name' => 'products-access',
            'guard_name' => 'web',
        ]);

        $this->category = \App\Models\Category::create([
            'name' => 'Test Kategori',
            'image' => '',
            'description' => '',
        ]);
    }

    private function adminUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo('products-access');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeProduct(string $title, int $i): Product
    {
        return Product::create([
            'title' => $title,
            'barcode' => 'TEST-'.$i,
            'sku' => 'SKU-'.$i,
            'image' => '',
            'description' => '',
            'buy_price' => 10000,
            'sell_price' => 15000,
            'stock' => 10,
            'category_id' => $this->category->id,
            'tax_type' => 'exclusive',
            'tax_rate' => 0,
            'min_stock' => 0,
            'max_stock' => 100,
            'is_composite' => false,
        ]);
    }

    public function test_unauthenticated_dashboard_route_redirects_to_login_not_500(): void
    {
        $this->get('/dashboard/products')
            ->assertRedirect(route('login'));
    }

    public function test_products_page_2_keeps_search_query_string(): void
    {
        for ($i = 1; $i <= 23; $i++) {
            $this->makeProduct('Produk Segar '.$i, $i);
        }
        $this->makeProduct('Minyak Goreng', 99);

        $response = $this->actingAs($this->adminUser())
            ->get('/dashboard/products?search=Segar&page=2');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $page->component('Dashboard/Products/Index')
                ->where('products.current_page', 2)
                ->where('products.last_page', 3)
                ->where('products.total', 23);

            // Pagination link ke halaman 2 harus menyertakan query search
            $links = $page->toArray()['props']['products']['links'] ?? [];
            $page2Link = collect($links)->first(fn ($l) => ($l['label'] ?? '') === '2');
            $this->assertNotNull($page2Link, 'Link halaman 2 tidak ditemukan');
            $this->assertStringContainsString('search=Segar', $page2Link['url']);
        });
    }

    public function test_per_page_query_is_honored(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->makeProduct('Produk '.$i, $i);
        }

        $response = $this->actingAs($this->adminUser())
            ->get('/dashboard/products?per_page=25');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('products.per_page', 25)
            ->where('products.total', 30)
        );
    }

    public function test_per_page_is_clamped_to_max_100(): void
    {
        $this->makeProduct('Produk A', 1);

        $response = $this->actingAs($this->adminUser())
            ->get('/dashboard/products?per_page=9999');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('products.per_page', 100)
        );
    }
}
