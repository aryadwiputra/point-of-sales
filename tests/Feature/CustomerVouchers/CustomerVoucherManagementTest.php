<?php

namespace Tests\Feature\CustomerVouchers;

use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerVoucherManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'customer-vouchers-access',
            'customer-vouchers-create',
            'customer-vouchers-update',
            'customer-vouchers-delete',
        ] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_customer_voucher_can_be_created_updated_and_deleted_with_audit_log(): void
    {
        $user = $this->createUserWithPermissions([
            'customer-vouchers-create',
            'customer-vouchers-update',
            'customer-vouchers-delete',
        ]);
        $customer = $this->createCustomer();

        $this->actingAs($user)->post(route('customer-vouchers.store'), [
            'customer_id' => $customer->id,
            'code' => '',
            'name' => 'Voucher Retensi',
            'discount_type' => CustomerVoucher::TYPE_FIXED_AMOUNT,
            'discount_value' => 15000,
            'minimum_order' => '',
            'is_active' => true,
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'expires_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'notes' => 'Promo personal',
        ])->assertRedirect(route('customer-vouchers.index'));

        $voucher = CustomerVoucher::query()->firstOrFail();

        $this->assertStringStartsWith('VCR-', $voucher->code);
        $this->assertSame(0, $voucher->minimum_order);
        $this->assertTrue($voucher->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'customer_voucher.created',
            'module' => 'customer_vouchers',
        ]);

        $this->actingAs($user)->put(route('customer-vouchers.update', $voucher), [
            'customer_id' => $customer->id,
            'code' => '',
            'name' => 'Voucher Retensi Update',
            'discount_type' => CustomerVoucher::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'minimum_order' => 25000,
            'is_active' => false,
            'starts_at' => null,
            'expires_at' => null,
            'notes' => null,
        ])->assertRedirect(route('customer-vouchers.index'));

        $voucher->refresh();

        $this->assertStringStartsWith('VCR-', $voucher->code);
        $this->assertSame('Voucher Retensi Update', $voucher->name);
        $this->assertSame(CustomerVoucher::TYPE_PERCENTAGE, $voucher->discount_type);
        $this->assertFalse($voucher->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'customer_voucher.updated',
            'module' => 'customer_vouchers',
        ]);

        $this->actingAs($user)
            ->delete(route('customer-vouchers.destroy', $voucher))
            ->assertRedirect();

        $this->assertDatabaseMissing('customer_vouchers', [
            'id' => $voucher->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'customer_voucher.deleted',
            'module' => 'customer_vouchers',
        ]);
    }

    public function test_percentage_customer_voucher_cannot_exceed_one_hundred_percent(): void
    {
        $user = $this->createUserWithPermissions(['customer-vouchers-create']);
        $customer = $this->createCustomer();

        $this->actingAs($user)->post(route('customer-vouchers.store'), [
            'customer_id' => $customer->id,
            'name' => 'Voucher Terlalu Besar',
            'discount_type' => CustomerVoucher::TYPE_PERCENTAGE,
            'discount_value' => 150,
            'minimum_order' => 0,
            'is_active' => true,
        ])->assertSessionHasErrors('discount_value');

        $this->assertDatabaseCount('customer_vouchers', 0);
    }

    private function createUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function createCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Customer Voucher',
            'no_telp' => '628510001',
            'address' => 'Jl. Voucher',
        ]);
    }
}
