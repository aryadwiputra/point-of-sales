<?php

namespace Tests\Feature\DineIn;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\DineArea;
use App\Models\DineBill;
use App\Models\DineOrder;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Setting;
use App\Models\TransactionTender;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\DineBillSettlementService;
use App\Services\DineSessionService;
use App\Support\Dine\DineSettlementContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DineSettlementPaymentTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private User $cashier;

    private Warehouse $warehouse;

    private Category $category;

    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->cashier->markEmailAsVerified();
        $this->warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $this->category = Category::create([
            'name' => 'Kategori Test',
            'image' => 'categories/test.jpg',
            'description' => 'Kategori untuk test',
        ]);
        $area = DineArea::create(['name' => 'Indoor', 'is_active' => true]);
        $this->table = DiningTable::create([
            'dine_area_id' => $area->id,
            'name' => 'Meja Payment',
            'is_active' => true,
            'capacity' => 4,
        ]);
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
    }

    private function makeBill(int $qty = 2, int $price = 10000): array
    {
        self::$seq++;
        $product = Product::create([
            'title' => 'Menu Pay '.self::$seq,
            'sku' => 'SKU-PAY-'.self::$seq,
            'buy_price' => 4000,
            'sell_price' => $price,
            'stock' => 10,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-PAY-'.self::$seq,
            'description' => 'Produk test',
            'tax_rate' => 0,
            'tax_type' => 'exclusive',
            'category_id' => $this->category->id,
        ]);
        $product->warehouses()->attach($this->warehouse->id, ['stock' => 10]);

        $session = app(DineSessionService::class)->open($this->table, 2, null, $this->cashier->id);
        $bill = DineBill::create([
            'dine_session_id' => $session->id,
            'sequence' => 1,
            'status' => DineBill::STATUS_OPEN,
            'subtotal' => $price * $qty,
            'grand_total' => $price * $qty,
        ]);
        $order = DineOrder::create([
            'dine_table_id' => $this->table->id,
            'dine_session_id' => $session->id,
            'access_token' => 'pay-'.uniqid(),
            'status' => DineOrder::STATUS_ACCEPTED,
            'source' => DineOrder::SOURCE_QR,
            'subtotal' => $price * $qty,
        ]);
        $order->items()->create([
            'dine_bill_id' => $bill->id,
            'product_id' => $product->id,
            'qty' => $qty,
            'price' => $price,
            'unit_cost' => 4000,
            'tax_type' => 'exclusive',
            'tax_rate' => 0,
        ]);

        return [$bill, $order, $product];
    }

    public function test_split_tender_creates_two_tender_rows(): void
    {
        [$bill] = $this->makeBill();
        $bank = BankAccount::active()->first();

        $result = app(DineBillSettlementService::class)->settle($bill, new DineSettlementContext(
            userId: $this->cashier->id,
            isCashPayment: false,
            useTenders: true,
            tenderInput: [
                ['method' => TransactionTender::METHOD_CASH, 'amount' => 10000, 'cash_received' => 10000],
                ['method' => TransactionTender::METHOD_BANK_TRANSFER, 'amount' => 10000, 'bank_account_id' => $bank->id],
            ],
        ));

        $this->assertSame('split', $result->transaction->payment_method);
        $this->assertSame(20000, (int) $result->transaction->grand_total);
        $this->assertSame(2, $result->transaction->tenders()->count());
        $this->assertSame(10000, (int) $result->transaction->cash);
    }

    public function test_pay_later_creates_receivable(): void
    {
        [$bill] = $this->makeBill();
        $customer = Customer::create([
            'name' => 'Pelanggan Nota',
            'no_telp' => '08123456789',
            'address' => 'Jl. Test No. 1',
        ]);

        $result = app(DineBillSettlementService::class)->settle($bill, new DineSettlementContext(
            userId: $this->cashier->id,
            customer: $customer,
            isPayLater: true,
            dueDate: now()->addWeek()->toDateString(),
            isCashPayment: false,
        ));

        $this->assertSame('pay_later', $result->transaction->payment_method);
        $this->assertSame('unpaid', $result->transaction->payment_status);
        $this->assertSame(1, Receivable::count());
        $this->assertSame($customer->id, Receivable::first()->customer_id);
    }

    public function test_manual_discount_above_threshold_puts_bill_pending_approval(): void
    {
        Setting::set('discount_approval_threshold', '1000');
        [$bill] = $this->makeBill();

        $result = app(DineBillSettlementService::class)->settle($bill, new DineSettlementContext(
            userId: $this->cashier->id,
            manualDiscount: 2000,
            isCashPayment: true,
            cashAmount: 20000,
        ));

        $this->assertTrue($result->needsDiscountApproval);
        $this->assertSame(DineBill::STATUS_PENDING_APPROVAL, $result->bill->status);
        $this->assertSame('pending_approval', $result->transaction->payment_status);
        $this->assertDatabaseHas('discount_approval_logs', [
            'transaction_id' => $result->transaction->id,
            'status' => 'pending',
        ]);
    }

    public function test_voucher_and_points_reduce_grand_total(): void
    {
        [$bill] = $this->makeBill();
        $customer = Customer::create([
            'name' => 'Member Test',
            'no_telp' => '081200000001',
            'address' => 'Jl. Test No. 2',
            'is_loyalty_member' => true,
            'loyalty_points' => 20,
            'member_code' => 'MBR-'.uniqid(),
        ]);
        $voucher = CustomerVoucher::create([
            'customer_id' => $customer->id,
            'code' => 'V-'.uniqid(),
            'name' => 'Potongan 1000',
            'discount_type' => CustomerVoucher::TYPE_FIXED_AMOUNT,
            'discount_value' => 1000,
            'minimum_order' => 0,
            'is_active' => true,
        ]);

        $result = app(DineBillSettlementService::class)->settle($bill, new DineSettlementContext(
            userId: $this->cashier->id,
            customer: $customer,
            voucher: $voucher,
            requestedRedeemPoints: 10,
            isCashPayment: true,
            cashAmount: 20000,
        ));

        $this->assertSame(2000, $result->discountTotal);
        $this->assertSame(18000, $result->grandTotal);
        $this->assertSame(1000, (int) $result->transaction->customer_voucher_discount);
        $this->assertSame(10, (int) $result->transaction->loyalty_points_redeemed);
    }
}
