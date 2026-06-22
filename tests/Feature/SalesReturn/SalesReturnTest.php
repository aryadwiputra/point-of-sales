<?php

namespace Tests\Feature\SalesReturn;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\SalesReturn;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SalesReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'transactions-access',
            'sales-returns-access',
            'sales-returns-create',
            'sales-returns-complete',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
        ] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_authorized_user_can_create_sales_return_draft_for_cash_transaction(): void
    {
        $user = $this->createUserWithPermissions([
            'transactions-access',
            'sales-returns-access',
            'sales-returns-create',
        ]);

        [$transaction, $detail] = $this->createTransaction($user);

        $response = $this
            ->actingAs($user)
            ->post(route('sales-returns.store', $transaction), [
                'return_type' => 'refund_cash',
                'notes' => 'Retur karena salah ukuran',
                'items' => [
                    [
                        'transaction_detail_id' => $detail->id,
                        'qty_return' => 1,
                        'return_reason' => 'Salah ukuran',
                        'restock_to_inventory' => true,
                    ],
                ],
            ]);

        $salesReturn = SalesReturn::first();

        $response->assertRedirect(route('sales-returns.show', $salesReturn));
        $this->assertNotNull($salesReturn);
        $this->assertSame('draft', $salesReturn->status);
        $this->assertSame(1, $salesReturn->items()->count());
        $this->assertSame(60000, $salesReturn->total_return_amount);
        $this->assertSame(60000, $salesReturn->refund_amount);
    }

    public function test_qty_return_cannot_exceed_remaining_quantity(): void
    {
        $user = $this->createUserWithPermissions([
            'transactions-access',
            'sales-returns-access',
            'sales-returns-create',
            'sales-returns-complete',
        ]);

        [$transaction, $detail, $product] = $this->createTransaction($user, qty: 2);

        $firstReturn = SalesReturn::create([
            'code' => 'SR-TEST-001',
            'transaction_id' => $transaction->id,
            'customer_id' => $transaction->customer_id,
            'cashier_id' => $user->id,
            'status' => 'completed',
            'return_type' => 'refund_cash',
            'refund_amount' => 60000,
            'credited_amount' => 0,
            'total_return_amount' => 60000,
            'completed_at' => now(),
        ]);

        $firstReturn->items()->create([
            'transaction_detail_id' => $detail->id,
            'product_id' => $product->id,
            'qty_sold' => 2,
            'qty_returned_before' => 0,
            'qty_return' => 1,
            'unit_price' => 60000,
            'subtotal' => 60000,
            'return_reason' => 'Retur pertama',
            'restock_to_inventory' => true,
        ]);

        $response = $this
            ->from(route('sales-returns.create', $transaction))
            ->actingAs($user)
            ->post(route('sales-returns.store', $transaction), [
                'return_type' => 'refund_cash',
                'items' => [
                    [
                        'transaction_detail_id' => $detail->id,
                        'qty_return' => 2,
                        'return_reason' => 'Melebihi sisa',
                        'restock_to_inventory' => true,
                    ],
                ],
            ]);

        $response->assertInvalid(['items']);
        $this->assertDatabaseCount('sales_returns', 1);
    }

    public function test_complete_sales_return_restocks_product_and_creates_mutation(): void
    {
        $user = $this->createUserWithPermissions([
            'transactions-access',
            'sales-returns-access',
            'sales-returns-create',
            'sales-returns-complete',
        ]);

        [$transaction, $detail, $product] = $this->createTransaction($user, qty: 1, stock: 4);
        $shift = $this->openShiftFor($user);

        $salesReturn = SalesReturn::create([
            'code' => 'SR-TEST-002',
            'transaction_id' => $transaction->id,
            'customer_id' => $transaction->customer_id,
            'cashier_id' => $user->id,
            'status' => 'draft',
            'return_type' => 'refund_cash',
            'refund_amount' => 60000,
            'credited_amount' => 0,
            'total_return_amount' => 60000,
        ]);

        $salesReturn->items()->create([
            'transaction_detail_id' => $detail->id,
            'product_id' => $product->id,
            'qty_sold' => 1,
            'qty_returned_before' => 0,
            'qty_return' => 1,
            'unit_price' => 60000,
            'subtotal' => 60000,
            'return_reason' => 'Barang dikembalikan',
            'restock_to_inventory' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('sales-returns.complete', $salesReturn));

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertDatabaseHas('sales_returns', [
            'id' => $salesReturn->id,
            'status' => 'completed',
            'refund_amount' => 60000,
            'cashier_shift_id' => $shift->id,
        ]);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'reference_type' => 'sales_return',
            'reference_id' => $salesReturn->id,
            'mutation_type' => 'in',
            'qty' => 1,
            'stock_before' => 4,
            'stock_after' => 5,
        ]);
        $this->assertDatabaseHas('profits', [
            'transaction_id' => $transaction->id,
            'total' => -15000,
        ]);
    }

    public function test_complete_sales_return_updates_receivable_total_and_creates_credit_on_overpayment(): void
    {
        $user = $this->createUserWithPermissions([
            'transactions-access',
            'sales-returns-access',
            'sales-returns-create',
            'sales-returns-complete',
        ]);

        [$transaction, $detail, $product, $customer] = $this->createTransaction(
            $user,
            qty: 1,
            stock: 3,
            paymentMethod: 'pay_later',
            paymentStatus: 'paid',
            withReceivable: true
        );
        $shift = $this->openShiftFor($user);

        $salesReturn = SalesReturn::create([
            'code' => 'SR-TEST-003',
            'transaction_id' => $transaction->id,
            'customer_id' => $customer->id,
            'cashier_id' => $user->id,
            'status' => 'draft',
            'return_type' => 'store_credit',
            'refund_amount' => 0,
            'credited_amount' => 60000,
            'total_return_amount' => 60000,
        ]);

        $salesReturn->items()->create([
            'transaction_detail_id' => $detail->id,
            'product_id' => $product->id,
            'qty_sold' => 1,
            'qty_returned_before' => 0,
            'qty_return' => 1,
            'unit_price' => 60000,
            'subtotal' => 60000,
            'return_reason' => 'Salah kirim',
            'restock_to_inventory' => true,
        ]);

        $this->actingAs($user)->post(route('sales-returns.complete', $salesReturn));

        $this->assertDatabaseHas('receivables', [
            'transaction_id' => $transaction->id,
            'total' => 0,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('customer_credits', [
            'customer_id' => $customer->id,
            'sales_return_id' => $salesReturn->id,
            'amount' => 60000,
            'balance' => 60000,
        ]);
        $this->assertDatabaseHas('sales_returns', [
            'id' => $salesReturn->id,
            'cashier_shift_id' => $shift->id,
        ]);
    }

    public function test_transaction_without_customer_forces_refund_cash(): void
    {
        $user = $this->createUserWithPermissions([
            'transactions-access',
            'sales-returns-access',
            'sales-returns-create',
        ]);

        [$transaction, $detail] = $this->createTransaction($user, withCustomer: false);

        $this->actingAs($user)->post(route('sales-returns.store', $transaction), [
            'return_type' => 'store_credit',
            'items' => [
                [
                    'transaction_detail_id' => $detail->id,
                    'qty_return' => 1,
                    'return_reason' => 'Barang dibatalkan',
                    'restock_to_inventory' => true,
                ],
            ],
        ]);

        $salesReturn = SalesReturn::first();

        $this->assertSame('refund_cash', $salesReturn->return_type);
    }

    public function test_completed_sales_return_cannot_be_updated(): void
    {
        $user = $this->createUserWithPermissions([
            'transactions-access',
            'sales-returns-access',
            'sales-returns-create',
        ]);

        [$transaction, $detail, $product] = $this->createTransaction($user);

        $salesReturn = SalesReturn::create([
            'code' => 'SR-TEST-004',
            'transaction_id' => $transaction->id,
            'customer_id' => $transaction->customer_id,
            'cashier_id' => $user->id,
            'status' => 'completed',
            'return_type' => 'refund_cash',
            'refund_amount' => 60000,
            'credited_amount' => 0,
            'total_return_amount' => 60000,
            'completed_at' => now(),
        ]);

        $salesReturn->items()->create([
            'transaction_detail_id' => $detail->id,
            'product_id' => $product->id,
            'qty_sold' => 1,
            'qty_returned_before' => 0,
            'qty_return' => 1,
            'unit_price' => 60000,
            'subtotal' => 60000,
            'return_reason' => 'Tidak jadi beli',
            'restock_to_inventory' => true,
        ]);

        $response = $this
            ->from(route('sales-returns.show', $salesReturn))
            ->actingAs($user)
            ->patch(route('sales-returns.update', $salesReturn), [
                'return_type' => 'refund_cash',
                'items' => [
                    [
                        'transaction_detail_id' => $detail->id,
                        'qty_return' => 1,
                        'return_reason' => 'Ubah alasan',
                        'restock_to_inventory' => true,
                    ],
                ],
            ]);

        $response->assertInvalid(['sales_return']);
    }

    private function createUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function createTransaction(
        User $user,
        int $qty = 1,
        int $stock = 10,
        bool $withCustomer = true,
        string $paymentMethod = 'cash',
        string $paymentStatus = 'paid',
        bool $withReceivable = false
    ): array {
        $category = Category::create([
            'name' => 'Kategori '.Str::upper(Str::random(5)),
            'description' => 'Kategori pengujian',
            'image' => 'category.png',
        ]);

        $product = Product::create([
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

        $customer = $withCustomer
            ? Customer::create([
                'name' => 'Customer Test',
                'no_telp' => '08123456789',
                'address' => 'Jalan Test',
            ])
            : null;

        $transaction = Transaction::create([
            'cashier_id' => $user->id,
            'cashier_shift_id' => null,
            'customer_id' => $customer?->id,
            'invoice' => 'TRX-'.Str::upper(Str::random(8)),
            'cash' => 60000 * $qty,
            'change' => 0,
            'discount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 60000 * $qty,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'payment_reference' => null,
            'payment_url' => null,
            'bank_account_id' => null,
        ]);

        $detail = $transaction->details()->create([
            'product_id' => $product->id,
            'qty' => $qty,
            'price' => 60000,
        ]);

        $transaction->profits()->create([
            'total' => 15000 * $qty,
        ]);

        if ($withReceivable) {
            Receivable::create([
                'customer_id' => $customer?->id,
                'transaction_id' => $transaction->id,
                'invoice' => $transaction->invoice,
                'total' => $transaction->grand_total,
                'paid' => $transaction->grand_total,
                'due_date' => now()->addDays(7),
                'status' => 'paid',
            ]);
        }

        return [$transaction->fresh(['details', 'receivable']), $detail, $product, $customer];
    }

    private function openShiftFor(User $user)
    {
        return \App\Models\CashierShift::create([
            'user_id' => $user->id,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'opening_cash' => 100000,
            'expected_cash' => 100000,
            'status' => 'open',
        ]);
    }
}
