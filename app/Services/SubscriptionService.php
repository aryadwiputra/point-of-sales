<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\PaymentSetting;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Payments\MidtransGateway;
use App\Services\Payments\XenditGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function __construct(
        private MidtransGateway $midtransGateway,
        private XenditGateway $xenditGateway,
    ) {}

    /**
     * Gateways configured at platform level (env), not per-outlet settings.
     *
     * @return array<int, string>
     */
    public function availableGateways(): array
    {
        $gateways = [];

        if (filled(config('services.midtrans.server_key'))) {
            $gateways[] = PaymentSetting::GATEWAY_MIDTRANS;
        }

        if (filled(config('services.xendit.secret_key'))) {
            $gateways[] = PaymentSetting::GATEWAY_XENDIT;
        }

        return $gateways;
    }

    public function defaultGateway(): ?string
    {
        return $this->availableGateways()[0] ?? null;
    }

    public function amountFor(Plan $plan, string $cycle): int
    {
        return (int) round($cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly);
    }

    public function endsAt(Carbon $start, string $cycle): Carbon
    {
        return $cycle === 'yearly'
            ? $start->copy()->addYear()
            : $start->copy()->addMonth();
    }

    /**
     * Start a subscription purchase. Free plans activate immediately; paid
     * plans create a pending record plus a gateway payment URL.
     *
     * @return array{status: string, subscription: TenantSubscription, payment_url: ?string}
     */
    public function subscribe(Tenant $tenant, Plan $plan, string $cycle, ?string $gateway = null): array
    {
        $amount = $this->amountFor($plan, $cycle);

        if ($amount > 0) {
            $gateway ??= $this->defaultGateway();

            if (! $gateway) {
                throw new PaymentGatewayException('Gateway pembayaran langganan belum dikonfigurasi. Hubungi admin platform.');
            }
        }

        $subscription = TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'amount' => $amount,
            'payment_status' => $amount > 0 ? 'pending' : 'paid',
            'payment_method' => $amount > 0 ? $gateway : null,
            'starts_at' => now(),
            // Paket gratis (Rp0, mis. bayar per transaksi) tidak kedaluwarsa.
            'ends_at' => $amount > 0 ? $this->endsAt(now(), $cycle) : null,
        ]);

        if ($amount <= 0) {
            $this->activate($subscription);

            return [
                'status' => 'active',
                'subscription' => $subscription->fresh(),
                'payment_url' => null,
            ];
        }

        $payment = $this->createGatewayPayment($subscription, $plan, $gateway);

        $subscription->update([
            'gateway_reference' => $payment['reference'] ?? null,
            'payment_url' => $payment['payment_url'] ?? null,
        ]);

        return [
            'status' => 'pending',
            'subscription' => $subscription->fresh(),
            'payment_url' => $payment['payment_url'] ?? null,
        ];
    }

    public function createGatewayPayment(TenantSubscription $subscription, Plan $plan, ?string $gateway = null): array
    {
        $gateway ??= $subscription->payment_method ?? $this->defaultGateway();

        $orderId = $this->orderId($subscription);
        $owner = $subscription->tenant?->owner;

        return match ($gateway) {
            PaymentSetting::GATEWAY_MIDTRANS => $this->midtransGateway->postSnap([
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (int) $subscription->amount,
                ],
                'customer_details' => [
                    'first_name' => $owner?->name ?? $subscription->tenant?->name ?? 'Tenant',
                    'email' => $owner?->email ?? config('mail.from.address'),
                    'phone' => $owner?->no_telp,
                ],
                'item_details' => [[
                    'id' => (string) $plan->id,
                    'name' => "Langganan {$plan->name} (".($subscription->billing_cycle === 'yearly' ? 'Tahunan' : 'Bulanan').')',
                    'price' => (int) $subscription->amount,
                    'quantity' => 1,
                ]],
                'callbacks' => [
                    'finish' => route('subscription.index'),
                ],
            ], [
                'enabled' => true,
                'server_key' => config('services.midtrans.server_key'),
                'is_production' => (bool) config('services.midtrans.is_production'),
            ]),
            PaymentSetting::GATEWAY_XENDIT => $this->xenditGateway->postInvoice([
                'external_id' => $orderId,
                'amount' => (int) $subscription->amount,
                'description' => "Langganan {$plan->name} - {$subscription->tenant?->name}",
                'customer' => [
                    'given_names' => $owner?->name ?? $subscription->tenant?->name ?? 'Tenant',
                    'email' => $owner?->email ?? config('mail.from.address'),
                    'mobile_number' => $owner?->no_telp,
                ],
                'success_redirect_url' => route('subscription.index'),
                'invoice_duration' => 86400,
            ], [
                'enabled' => true,
                'secret_key' => config('services.xendit.secret_key'),
            ]),
            default => throw new PaymentGatewayException("Gateway {$gateway} belum didukung untuk langganan."),
        };
    }

    public function orderId(TenantSubscription $subscription): string
    {
        return 'SUB-'.$subscription->id;
    }

    public function findByOrderId(string $orderId): ?TenantSubscription
    {
        if (! preg_match('/^SUB-(\d+)$/', $orderId, $matches)) {
            return null;
        }

        return TenantSubscription::with(['tenant.owner', 'plan'])->find((int) $matches[1]);
    }

    /**
     * Mark a subscription paid and apply the plan to its tenant.
     */
    public function activate(TenantSubscription $subscription, ?string $reference = null): TenantSubscription
    {
        return DB::transaction(function () use ($subscription, $reference) {
            /** @var TenantSubscription $subscription */
            $subscription = TenantSubscription::with('tenant')->lockForUpdate()->find($subscription->id);

            if ($subscription->payment_status === 'paid' && $subscription->paid_at !== null) {
                return $subscription;
            }

            $startsAt = now();
            // Paket gratis (Rp0, mis. bayar per transaksi) aktif tanpa batas waktu.
            $endsAt = $subscription->amount > 0
                ? $this->endsAt($startsAt, $subscription->billing_cycle)
                : null;

            $subscription->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'gateway_reference' => $reference ?? $subscription->gateway_reference,
                'payment_url' => null,
            ]);

            if ($subscription->tenant) {
                $subscription->tenant->update([
                    'plan_id' => $subscription->plan_id,
                    'status' => 'active',
                    'subscription_ends_at' => $endsAt,
                ]);
            }

            Log::info('Subscription activated', [
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'ends_at' => $endsAt?->toIso8601String(),
            ]);

            return $subscription->fresh();
        });
    }

    public function markFailed(TenantSubscription $subscription, string $status = 'failed'): TenantSubscription
    {
        $subscription->update([
            'payment_status' => $status,
            'payment_url' => null,
        ]);

        return $subscription;
    }
}
