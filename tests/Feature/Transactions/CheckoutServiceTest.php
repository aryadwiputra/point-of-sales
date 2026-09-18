<?php

namespace Tests\Feature\Transactions;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionTender;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\CheckoutService;
use App\Support\Checkout\CheckoutContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected Warehouse $warehouse;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->cashier->markEmailAsVerified();

        $category = Category::create([
            'name' => 'Kategori Test',
            'image' => 'categories/test.jpg',
            'description' => 'Kategori untuk test',
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->product = Product::create([
            'title' => 'Produk Test',
            'sku' => 'SKU-CO-'.uniqid(),
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 100,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-CO-'.uniqid(),
            'description' => 'Deskripsi produk test',
            'tax_rate' => 0,
            'category_id' => $category->id,
        ]);
        $this->warehouse->products()->attach($this->product->id, ['stock' => 100]);

        app(CashierShiftService::class)->openShift(
            $this->cashier,
            $this->cashier,
            0,
            null,
            $this->warehouse->id,
        );

        BankAccount::create([
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_name' => 'Toko Test',
            'is_active' => true,
        ]);

        $this->actingAs($this->cashier);
    }

    private function payCash(int $amount = 10000): CheckoutContext
    {
        $this->post(route('transactions.addToCart'), [
            'product_id' => $this->product->id,
            'sell_price' => 10000,
            'qty' => 1,
        ]);

        return new CheckoutContext(
            userId: $this->cashier->id,
            customer: null,
            voucher: null,
            manualDiscount: 0,
            shippingCost: 0,
            requestedRedeemPoints: 0,
            isPayLater: false,
            dueDate: null,
            orderType: null,
            note: null,
            customerNpwp: null,
            isCashPayment: true,
            cashAmount: $amount,
            paymentGateway: null,
            useTenders: false,
            tenderInput: [],
            outlet: null,
            bankAccountId: null,
        );
    }

    public function test_cash_below_total_throws_validation(): void
    {
        $context = $this->payCash(9999);

        try {
            app(CheckoutService::class)->execute($context);
            $this->fail('Expected ValidationException for cash below total.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cash', $e->errors());
            $this->assertSame(0, Transaction::count(), 'No transaction should be created on validation failure.');
        }
    }

    public function test_cash_equal_total_succeeds(): void
    {
        $context = $this->payCash(10000);
        $result = app(CheckoutService::class)->execute($context);

        $this->assertSame(10000, $result->transaction->grand_total);
        $this->assertSame('cash', $result->paymentMethod);
        $this->assertSame('paid', $result->paymentStatus);
        $this->assertFalse($result->needsDiscountApproval);
        $this->assertSame(99, $this->warehouse->products()->where('product_id', $this->product->id)->first()->pivot->stock);
    }

    public function test_split_tender_creates_two_rows_and_marks_parent_split(): void
    {
        $this->post(route('transactions.addToCart'), [
            'product_id' => $this->product->id,
            'sell_price' => 10000,
            'qty' => 1,
        ]);

        $bankAccount = BankAccount::active()->first();

        $context = new CheckoutContext(
            userId: $this->cashier->id,
            customer: null,
            voucher: null,
            manualDiscount: 0,
            shippingCost: 0,
            requestedRedeemPoints: 0,
            isPayLater: false,
            dueDate: null,
            orderType: null,
            note: null,
            customerNpwp: null,
            isCashPayment: false,
            cashAmount: 0,
            paymentGateway: null,
            useTenders: true,
            tenderInput: [
                ['method' => TransactionTender::METHOD_CASH, 'amount' => 5000, 'cash_received' => 5000],
                ['method' => TransactionTender::METHOD_BANK_TRANSFER, 'amount' => 5000, 'bank_account_id' => $bankAccount->id],
            ],
            outlet: null,
            bankAccountId: $bankAccount->id,
        );

        $result = app(CheckoutService::class)->execute($context);

        $this->assertSame('split', $result->paymentMethod);
        $this->assertSame('paid', $result->paymentStatus);
        $this->assertSame(2, $result->transaction->tenders()->count());
        $this->assertSame(10000, (int) $result->transaction->tenders()->sum('amount'));
        $this->assertSame(5000, (int) $result->transaction->cash);
    }

    public function test_empty_cart_aborts_422(): void
    {
        $context = new CheckoutContext(
            userId: $this->cashier->id,
            customer: null,
            voucher: null,
            manualDiscount: 0,
            shippingCost: 0,
            requestedRedeemPoints: 0,
            isPayLater: false,
            dueDate: null,
            orderType: null,
            note: null,
            customerNpwp: null,
            isCashPayment: true,
            cashAmount: 10000,
            paymentGateway: null,
            useTenders: false,
            tenderInput: [],
            outlet: null,
            bankAccountId: null,
        );

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CheckoutService::class)->execute($context);
    }
}
