<?php

namespace Tests\Feature\Transactions;

use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiscountApprovalStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->admin = User::where('email', 'arya@gmail.com')->first();
        $this->admin->markEmailAsVerified();
        $this->actingAs($this->admin);

        $this->warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private static int $seq = 0;

    private function makeBankAccount(): BankAccount
    {
        return BankAccount::create([
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_name' => 'Toko',
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function makeTransaction(array $overrides = []): Transaction
    {
        self::$seq++;

        return Transaction::create(array_merge([
            'cashier_id' => $this->admin->id,
            'cashier_shift_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'invoice' => 'INV-APPROVE-'.Str::upper(Str::random(10)),
            'grand_total' => 100000,
            'discount' => 20000,
            'cash' => 100000,
            'change' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'pending_approval',
            'discount_approval_status' => 'pending',
            'tax_rate' => 0,
            'tax_total' => 0,
        ], $overrides));
    }

    private function confirmPassword(): void
    {
        $this->session(['auth.password_confirmed_at' => time()]);
    }

    public function test_approving_cash_transaction_sets_paid_and_keeps_discount(): void
    {
        $transaction = $this->makeTransaction();

        $this->from(route('dashboard.access'))
            ->post(route('discount-approvals.approve', $transaction))
            ->assertSessionHas('success');

        $transaction = $transaction->fresh();
        $this->assertEquals('approved', $transaction->discount_approval_status);
        $this->assertEquals('paid', $transaction->payment_status);
        $this->assertEquals(20000, $transaction->discount);
        $this->assertEquals(100000, $transaction->grand_total);
    }

    public function test_approving_bank_transfer_transaction_stays_pending(): void
    {
        $transaction = $this->makeTransaction([
            'payment_method' => 'bank_transfer',
            'bank_account_id' => null,
            'payment_status' => 'pending_approval',
        ]);

        $this->from(route('dashboard.access'))
            ->post(route('discount-approvals.approve', $transaction))
            ->assertSessionHas('success');

        $transaction = $transaction->fresh();
        $this->assertEquals('approved', $transaction->discount_approval_status);
        $this->assertEquals('pending', $transaction->payment_status);
        $this->assertEquals(20000, $transaction->discount);
    }

    public function test_denying_transaction_removes_discount_and_marks_unpaid(): void
    {
        $transaction = $this->makeTransaction();

        $this->from(route('dashboard.access'))
            ->post(route('discount-approvals.deny', $transaction), ['notes' => 'Melebihi limit'])
            ->assertSessionHas('success');

        $transaction = $transaction->fresh();
        $this->assertEquals('denied', $transaction->discount_approval_status);
        $this->assertEquals('unpaid', $transaction->payment_status);
        $this->assertEquals(0, $transaction->discount);
        $this->assertEquals(120000, $transaction->grand_total);
    }

    public function test_approving_already_resolved_transaction_returns_404(): void
    {
        $transaction = $this->makeTransaction(['discount_approval_status' => 'approved']);

        $this->post(route('discount-approvals.approve', $transaction))
            ->assertNotFound();
    }

    public function test_confirm_payment_marks_bank_transfer_pending_as_paid(): void
    {
        $this->confirmPassword();

        $transaction = $this->makeTransaction([
            'payment_method' => 'bank_transfer',
            'bank_account_id' => $this->makeBankAccount()->id,
            'payment_status' => 'pending',
            'discount_approval_status' => null,
        ]);

        $this->from(route('dashboard.access'))
            ->patch(route('transactions.confirm-payment', $transaction))
            ->assertSessionHas('success');

        $this->assertEquals('paid', $transaction->fresh()->payment_status);
    }

    public function test_confirm_payment_rejects_cash_transaction(): void
    {
        $this->confirmPassword();

        $transaction = $this->makeTransaction([
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'discount_approval_status' => null,
        ]);

        $this->from(route('dashboard.access'))
            ->patch(route('transactions.confirm-payment', $transaction))
            ->assertSessionHas('error');

        $this->assertEquals('pending', $transaction->fresh()->payment_status);
    }

    public function test_confirm_payment_rejects_bank_transfer_without_bank_account(): void
    {
        $this->confirmPassword();

        $transaction = $this->makeTransaction([
            'payment_method' => 'bank_transfer',
            'bank_account_id' => null,
            'payment_status' => 'pending',
            'discount_approval_status' => null,
        ]);

        $this->from(route('dashboard.access'))
            ->patch(route('transactions.confirm-payment', $transaction))
            ->assertSessionHas('error');

        $this->assertEquals('pending', $transaction->fresh()->payment_status);
    }

    public function test_confirm_payment_rejects_paid_transaction(): void
    {
        $this->confirmPassword();

        $transaction = $this->makeTransaction([
            'payment_method' => 'bank_transfer',
            'bank_account_id' => $this->makeBankAccount()->id,
            'payment_status' => 'paid',
            'discount_approval_status' => null,
        ]);

        $this->from(route('dashboard.access'))
            ->patch(route('transactions.confirm-payment', $transaction))
            ->assertSessionHas('error');

        $this->assertEquals('paid', $transaction->fresh()->payment_status);
    }
}
