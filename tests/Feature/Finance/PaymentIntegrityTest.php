<?php

namespace Tests\Feature\Finance;

use App\Models\Customer;
use App\Models\Payable;
use App\Models\PayablePayment;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->admin = User::where('email', 'arya@gmail.com')->first();
        $this->admin->markEmailAsVerified();
        $this->actingAs($this->admin);
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Customer Tagihan',
            'no_telp' => '628111000001',
            'address' => 'Jl. Test',
        ]);
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::create([
            'name' => 'Supplier Tagihan',
            'phone' => '081234567890',
            'email' => 'supplier@test.com',
        ]);
    }

    private function makeReceivable(float $total = 100000): Receivable
    {
        return Receivable::create([
            'customer_id' => $this->makeCustomer()->id,
            'invoice' => 'RCV-'.uniqid(),
            'total' => $total,
            'paid' => 0,
            'due_date' => now()->addDays(7),
            'status' => 'unpaid',
        ]);
    }

    private function makePayable(float $total = 100000): Payable
    {
        return Payable::create([
            'supplier_id' => $this->makeSupplier()->id,
            'document_number' => 'INV-'.uniqid(),
            'total' => $total,
            'paid' => 0,
            'due_date' => now()->addDays(7),
            'status' => 'unpaid',
        ]);
    }

    private function payReceivablePayload(float $amount): array
    {
        return [
            'amount' => $amount,
            'paid_at' => now()->toDateString(),
            'method' => 'cash',
            'note' => 'Bayar piutang',
        ];
    }

    private function payPayablePayload(float $amount): array
    {
        return [
            'amount' => $amount,
            'paid_at' => now()->toDateString(),
            'method' => 'cash',
            'note' => 'Bayar hutang',
        ];
    }

    public function test_receivable_overpay_rejected(): void
    {
        $receivable = $this->makeReceivable(100000);

        $this->from(route('receivables.show', $receivable))
            ->post(route('receivables.pay', $receivable), $this->payReceivablePayload(150000))
            ->assertSessionHasErrors('amount');

        $this->assertEquals(0, ReceivablePayment::count());
        $this->assertEquals(0, $receivable->fresh()->paid);
        $this->assertEquals('unpaid', $receivable->fresh()->status);
    }

    public function test_receivable_exact_pay_marks_paid(): void
    {
        $receivable = $this->makeReceivable(100000);

        $this->post(route('receivables.pay', $receivable), $this->payReceivablePayload(100000))
            ->assertRedirect(route('receivables.show', $receivable))
            ->assertSessionHas('success');

        $receivable = $receivable->fresh();
        $this->assertEquals(100000, $receivable->paid);
        $this->assertEquals('paid', $receivable->status);
        $this->assertDatabaseHas('receivable_payments', [
            'receivable_id' => $receivable->id,
            'amount' => 100000,
        ]);
    }

    public function test_receivable_partial_pay_sets_partial_status(): void
    {
        $receivable = $this->makeReceivable(100000);

        $this->post(route('receivables.pay', $receivable), $this->payReceivablePayload(40000))
            ->assertSessionHas('success');

        $receivable = $receivable->fresh();
        $this->assertEquals(40000, $receivable->paid);
        $this->assertEquals('partial', $receivable->status);
    }

    public function test_receivable_second_pay_cannot_exceed_remaining(): void
    {
        $receivable = $this->makeReceivable(100000);

        $this->post(route('receivables.pay', $receivable), $this->payReceivablePayload(70000))
            ->assertSessionHas('success');

        $this->from(route('receivables.show', $receivable))
            ->post(route('receivables.pay', $receivable), $this->payReceivablePayload(50000))
            ->assertSessionHasErrors('amount');

        $receivable = $receivable->fresh();
        $this->assertEquals(70000, $receivable->paid);
        $this->assertEquals('partial', $receivable->status);
        $this->assertEquals(1, ReceivablePayment::count());
    }

    public function test_payable_overpay_rejected(): void
    {
        $payable = $this->makePayable(100000);

        $this->from(route('payables.show', $payable))
            ->post(route('payables.pay', $payable), $this->payPayablePayload(120000))
            ->assertSessionHasErrors('amount');

        $this->assertEquals(0, PayablePayment::count());
        $this->assertEquals(0, $payable->fresh()->paid);
        $this->assertEquals('unpaid', $payable->fresh()->status);
    }

    public function test_payable_exact_pay_marks_paid(): void
    {
        $payable = $this->makePayable(100000);

        $this->post(route('payables.pay', $payable), $this->payPayablePayload(100000))
            ->assertRedirect(route('payables.show', $payable))
            ->assertSessionHas('success');

        $payable = $payable->fresh();
        $this->assertEquals(100000, $payable->paid);
        $this->assertEquals('paid', $payable->status);
        $this->assertDatabaseHas('payable_payments', [
            'payable_id' => $payable->id,
            'amount' => 100000,
        ]);
    }

    public function test_payable_partial_pay_sets_partial_status(): void
    {
        $payable = $this->makePayable(100000);

        $this->post(route('payables.pay', $payable), $this->payPayablePayload(25000))
            ->assertSessionHas('success');

        $payable = $payable->fresh();
        $this->assertEquals(25000, $payable->paid);
        $this->assertEquals('partial', $payable->status);
    }

    public function test_payable_second_pay_cannot_exceed_remaining(): void
    {
        $payable = $this->makePayable(100000);

        $this->post(route('payables.pay', $payable), $this->payPayablePayload(80000))
            ->assertSessionHas('success');

        $this->from(route('payables.show', $payable))
            ->post(route('payables.pay', $payable), $this->payPayablePayload(30000))
            ->assertSessionHasErrors('amount');

        $payable = $payable->fresh();
        $this->assertEquals(80000, $payable->paid);
        $this->assertEquals('partial', $payable->status);
        $this->assertEquals(1, PayablePayment::count());
    }
}
