<?php

namespace App\Http\Controllers\Apps;

use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\QuotaService;
use App\Services\SubscriptionService;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly QuotaService $quotaService,
    ) {}

    public function index(): Response
    {
        $tenant = TenantContext::get();

        $plans = Plan::where('is_active', true)
            ->orderBy('price_monthly')
            ->get([
                'id', 'name', 'slug', 'description', 'price_monthly', 'price_yearly',
                'billing_type', 'fee_type', 'fee_value', 'fee_min', 'fee_max', 'fee_rounding', 'fee_methods',
                'max_outlets', 'max_users', 'max_products', 'max_customers', 'features',
            ]);

        if (! $tenant) {
            return Inertia::render('Dashboard/Subscription/Index', [
                'tenant' => null,
                'plans' => $plans,
                'usage' => $this->quotaService->getUsageSummary(),
                'subscriptions' => [],
                'gateways' => $this->subscriptionService->availableGateways(),
            ]);
        }

        $tenant->loadMissing(['plan', 'owner:id,name,email']);

        $subscriptions = $tenant->subscriptions()
            ->with('plan:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($subscription) => [
                'id' => $subscription->id,
                'plan' => $subscription->plan?->name,
                'billing_cycle' => $subscription->billing_cycle,
                'amount' => (int) round($subscription->amount),
                'payment_status' => $subscription->payment_status,
                'payment_method' => $subscription->payment_method,
                'payment_url' => $subscription->payment_url,
                'paid_at' => $subscription->paid_at?->toIso8601String(),
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
            ]);

        return Inertia::render('Dashboard/Subscription/Index', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'subdomain' => $tenant->subdomain,
                'status' => $tenant->status,
                'plan_id' => $tenant->plan_id,
                'plan' => $tenant->plan?->only(['id', 'name', 'slug', 'features']),
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                'subscription_ends_at' => $tenant->subscription_ends_at?->toIso8601String(),
                'is_active' => $tenant->isActive(),
            ],
            'plans' => $plans,
            'usage' => $this->quotaService->getUsageSummary(),
            'subscriptions' => $subscriptions,
            'gateways' => $this->subscriptionService->availableGateways(),
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();

        abort_unless($tenant, 403, 'Halaman langganan hanya tersedia untuk akun tenant.');

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        if ($plan->id === $tenant->plan_id && $tenant->isActive()) {
            return back()->with('error', 'Anda sudah berlangganan paket ini.');
        }

        if ($message = $this->downgradeConflictMessage($plan)) {
            return back()->with('error', $message);
        }

        try {
            $result = $this->subscriptionService->subscribe($tenant, $plan, $validated['billing_cycle']);
        } catch (PaymentGatewayException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['payment_url']) {
            return redirect()->away($result['payment_url']);
        }

        return back()->with('success', "Paket {$plan->name} berhasil diaktifkan.");
    }

    protected function downgradeConflictMessage(Plan $plan): ?string
    {
        $usage = $this->quotaService->getUsageSummary();

        $checks = [
            'outlets' => 'cabang',
            'users' => 'pengguna',
            'products' => 'produk',
            'customers' => 'pelanggan',
        ];

        foreach ($checks as $key => $label) {
            $current = $usage[$key]['current'] ?? 0;
            $max = $plan->{"max_{$key}"};

            if ($max !== null && $current > $max) {
                return "Paket {$plan->name} hanya mendukung {$max} {$label}, sedangkan saat ini Anda memakai {$current}. Kurangi data terlebih dahulu atau pilih paket yang lebih besar.";
            }
        }

        return null;
    }
}
