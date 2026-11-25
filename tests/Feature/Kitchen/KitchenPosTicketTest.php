<?php

namespace Tests\Feature\Kitchen;

use App\Models\Cart;
use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\KitchenStationCategory;
use App\Models\KitchenTicket;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\CheckoutService;
use App\Support\Checkout\CheckoutContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenPosTicketTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $this->cashier->markEmailAsVerified();
        $this->actingAs($this->cashier);

        $outlet = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->warehouse = Warehouse::create([
            'outlet_id' => $outlet->id,
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $category = Category::create(['name' => 'Makanan', 'image' => 'categories/x.jpg', 'description' => 'Makanan']);
        $this->product = Product::create([
            'image' => 'products/test.jpg',
            'barcode' => 'KIT-POS-1',
            'sku' => 'KIT-POS-1',
            'title' => 'Ayam Geprek',
            'description' => 'Test',
            'category_id' => $category->id,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 20,
            'tax_rate' => 0,
        ]);
        $this->warehouse->products()->attach($this->product->id, ['stock' => 20]);
        $station = KitchenStation::create(['outlet_id' => $outlet->id, 'name' => 'Hot', 'sort_order' => 1, 'is_active' => true]);
        KitchenStationCategory::create([
            'kitchen_station_id' => $station->id,
            'category_id' => $category->id,
            'outlet_id' => $outlet->id,
        ]);
        app(CashierShiftService::class)->openShift($this->cashier, $this->cashier, 0, null, $this->warehouse->id);
    }

    private function checkout(string $orderType): void
    {
        Cart::create([
            'cashier_id' => $this->cashier->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'price' => 10000,
        ]);

        app(CheckoutService::class)->execute(new CheckoutContext(
            userId: $this->cashier->id,
            customer: null,
            voucher: null,
            manualDiscount: 0,
            shippingCost: 0,
            requestedRedeemPoints: 0,
            isPayLater: false,
            dueDate: null,
            orderType: $orderType,
            note: null,
            customerNpwp: null,
            isCashPayment: true,
            cashAmount: 10000,
            paymentGateway: null,
            useTenders: false,
            tenderInput: [],
            outlet: null,
            bankAccountId: null,
        ));
    }

    public function test_takeaway_and_delivery_create_tickets_but_in_store_does_not(): void
    {
        $this->checkout('takeaway');
        $this->assertSame(1, KitchenTicket::where('order_type', 'takeaway')->count());

        $this->checkout('delivery');
        $this->assertSame(1, KitchenTicket::where('order_type', 'delivery')->count());

        $this->checkout('in_store');
        $this->assertSame(0, KitchenTicket::where('order_type', 'in_store')->count());
    }

    public function test_disabled_kitchen_creates_no_checkout_ticket(): void
    {
        Setting::set('kitchen_enabled', '0');

        $this->checkout('takeaway');

        $this->assertSame(0, KitchenTicket::count());
        Setting::set('kitchen_enabled', '1');
    }
}
