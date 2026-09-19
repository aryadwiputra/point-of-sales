<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\PaymentSetting;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicPortalPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_receivable_payment_uses_transaction_outlet_gateway_settings(): void
    {
        $outletA = Outlet::create(['code' => 'PPA', 'name' => 'Portal A']);
        $outletB = Outlet::create(['code' => 'PPB', 'name' => 'Portal B']);
        $warehouse = Warehouse::create([
            'code' => 'WH-PPA',
            'name' => 'Warehouse A',
            'type' => 'branch',
            'is_active' => true,
            'sort_order' => 0,
            'outlet_id' => $outletA->id,
        ]);

        PaymentSetting::create([
            'outlet_id' => $outletA->id,
            'default_gateway' => 'midtrans',
            'midtrans_enabled' => true,
            'midtrans_server_key' => 'outlet-a-server-key',
            'midtrans_client_key' => 'outlet-a-client-key',
        ]);
        PaymentSetting::create([
            'outlet_id' => $outletB->id,
            'default_gateway' => 'midtrans',
            'midtrans_enabled' => true,
            'midtrans_server_key' => 'outlet-b-server-key',
            'midtrans_client_key' => 'outlet-b-client-key',
        ]);

        $transaction = Transaction::create([
            'cashier_id' => User::factory()->create()->id,
            'warehouse_id' => $warehouse->id,
            'invoice' => 'INV-PORTAL-PAY',
            'cash' => 0,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 25000,
            'total' => 25000,
            'payment_method' => 'pay_later',
            'payment_status' => 'unpaid',
        ]);
        $receivable = Receivable::create([
            'transaction_id' => $transaction->id,
            'invoice' => 'INV-PORTAL-PAY',
            'due_date' => now()->addDays(3),
            'total' => 25000,
            'paid' => 0,
            'status' => 'partial',
        ]);

        $gateway = $this->mock(PaymentGatewayManager::class);
        $gateway->shouldReceive('createPayment')
            ->once()
            ->withArgs(function ($actualTransaction, $gatewayName, $setting) use ($transaction, $outletA) {
                return $actualTransaction->is($transaction)
                    && $gatewayName === 'midtrans'
                    && $setting->outlet_id === $outletA->id;
            })
            ->andReturn(['payment_url' => 'https://payments.test/portal']);

        $response = $this->post(route('portal.receivable.pay', [
            'receivable' => $receivable,
            'token' => $transaction->access_token,
        ]));

        $response->assertRedirect('https://payments.test/portal');
    }
}
