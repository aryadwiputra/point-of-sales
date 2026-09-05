<?php

namespace Tests\Feature\Loyalty;

use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LoyaltyService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerVoucherConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
    }

    private function createCustomer(string $code): Customer
    {
        return Customer::create([
            'name' => 'Member '.$code,
            'no_telp' => '628777000'.random_int(100, 999),
            'address' => 'Jl. Voucher',
            'is_loyalty_member' => true,
            'member_code' => 'MEM-'.$code,
            'loyalty_points' => 0,
        ]);
    }

    private function createVoucher(Customer $customer, string $code): CustomerVoucher
    {
        return CustomerVoucher::create([
            'customer_id' => $customer->id,
            'code' => $code,
            'name' => 'Voucher '.$code,
            'discount_type' => CustomerVoucher::TYPE_FIXED_AMOUNT,
            'discount_value' => 10000,
            'minimum_order' => 50000,
            'is_active' => true,
        ]);
    }

    private function makeTransaction(Customer $customer, string $voucherCode): Transaction
    {
        return Transaction::create([
            'cashier_id' => User::factory()->create()->id,
            'invoice' => 'INV-'.Str::upper(Str::random(8)),
            'customer_id' => $customer->id,
            'customer_voucher_code' => $voucherCode,
            'customer_voucher_discount' => 10000,
            'discount' => 0,
            'grand_total' => 50000,
            'cash' => 50000,
            'change' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'tax_rate' => 0,
            'tax_total' => 0,
        ]);
    }

    public function test_finalize_claims_voucher_and_marks_it_used(): void
    {
        $customer = $this->createCustomer('ONE');
        $voucher = $this->createVoucher($customer, 'VCR-CLAIM');
        $transaction = $this->makeTransaction($customer, 'VCR-CLAIM');

        app(LoyaltyService::class)->finalizeTransaction($transaction, $customer, ['voucher' => $voucher]);

        $voucher->refresh();
        $this->assertTrue($voucher->is_used);
        $this->assertSame($transaction->id, $voucher->used_transaction_id);
    }

    public function test_second_finalize_with_same_voucher_throws_atomically(): void
    {
        $customer = $this->createCustomer('TWO');
        $voucher = $this->createVoucher($customer, 'VCR-RACE');
        $transaction = $this->makeTransaction($customer, 'VCR-RACE');

        $service = app(LoyaltyService::class);

        // First concurrent checkout claims the voucher
        $service->finalizeTransaction($transaction, $customer, ['voucher' => $voucher]);

        $voucher->refresh();
        $this->assertTrue($voucher->is_used);
        $this->assertSame($transaction->id, $voucher->used_transaction_id);

        // Second concurrent finalize of the same single-use voucher must fail atomically
        try {
            $service->finalizeTransaction($transaction, $customer, ['voucher' => $voucher]);
            $this->fail('Expected ValidationException for double voucher redemption.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('voucher_code', $e->errors());
        }

        // No duplicate claim recorded
        $this->assertSame(1, CustomerVoucher::where('code', 'VCR-RACE')->where('is_used', true)->count());
    }
}
