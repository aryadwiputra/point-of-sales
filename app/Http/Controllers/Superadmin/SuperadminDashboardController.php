<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class SuperadminDashboardController extends Controller
{
    public function index(): Response
    {
        return TenantContext::withoutScope(function () {
            $totalTenants = Tenant::count();
            $activeTenants = Tenant::where('status', 'active')->count();
            $trialTenants = Tenant::where('status', 'trial')->count();
            $suspendedTenants = Tenant::where('status', 'suspended')->count();
            $expiredTenants = Tenant::where('status', 'expired')->count();

            // Estimated MRR (sum of price_monthly for active tenants)
            $mrr = Tenant::where('status', 'active')
                ->join('plans', 'tenants.plan_id', '=', 'plans.id')
                ->sum('plans.price_monthly');

            $plans = Plan::withCount('tenants')->get();

            $recentTenants = Tenant::with(['plan', 'owner:id,name,email'])
                ->latest()
                ->limit(6)
                ->get();

            $recentSubscriptions = TenantSubscription::with(['tenant:id,name,subdomain', 'plan:id,name'])
                ->latest()
                ->limit(6)
                ->get();

            return Inertia::render('Superadmin/Dashboard', [
                'metrics' => [
                    'totalTenants' => $totalTenants,
                    'activeTenants' => $activeTenants,
                    'trialTenants' => $trialTenants,
                    'suspendedTenants' => $suspendedTenants,
                    'expiredTenants' => $expiredTenants,
                    'mrr' => (float) $mrr,
                    'totalPlatformUsers' => User::count(),
                ],
                'plans' => $plans,
                'recentTenants' => $recentTenants,
                'recentSubscriptions' => $recentSubscriptions,
            ]);
        });
    }
}
