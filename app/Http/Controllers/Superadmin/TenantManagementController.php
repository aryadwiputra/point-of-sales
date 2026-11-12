<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class TenantManagementController extends Controller
{
    public function index(Request $request): Response
    {
        return TenantContext::withoutScope(function () use ($request) {
            $query = Tenant::with(['plan', 'owner:id,name,email'])
                ->withCount(['outlets', 'users', 'products']);

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('subdomain', 'like', "%{$search}%")
                        ->orWhereHas('owner', function ($ownerQuery) use ($search) {
                            $ownerQuery->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            }

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            if ($planId = $request->input('plan_id')) {
                $query->where('plan_id', $planId);
            }

            $tenants = $query->latest()->paginate(15)->withQueryString();
            $plans = Plan::where('is_active', true)->get(['id', 'name', 'slug']);

            return Inertia::render('Superadmin/Tenants/Index', [
                'tenants' => $tenants,
                'plans' => $plans,
                'filters' => $request->only(['search', 'status', 'plan_id']),
            ]);
        });
    }

    public function show(Tenant $tenant): Response
    {
        return TenantContext::withoutScope(function () use ($tenant) {
            $tenant->load(['plan', 'owner:id,name,email', 'subscriptions' => function ($q) {
                $q->latest()->limit(10);
            }]);

            $stats = [
                'outlets_count' => $tenant->outlets()->count(),
                'users_count' => $tenant->users()->count(),
                'products_count' => $tenant->products()->count(),
                'customers_count' => $tenant->customers()->count(),
            ];

            $plans = Plan::where('is_active', true)->get(['id', 'name', 'slug', 'price_monthly']);

            return Inertia::render('Superadmin/Tenants/Show', [
                'tenant' => $tenant,
                'stats' => $stats,
                'plans' => $plans,
            ]);
        });
    }

    public function updateStatus(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:active,trial,suspended,expired,cancelled',
        ]);

        $tenant->update(['status' => $validated['status']]);

        return back()->with('success', "Status tenant {$tenant->name} berhasil diubah menjadi {$validated['status']}.");
    }

    public function extendSubscription(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:3650',
        ]);

        $baseDate = ($tenant->subscription_ends_at && $tenant->subscription_ends_at->isFuture())
            ? $tenant->subscription_ends_at
            : now();

        $newEndDate = $baseDate->copy()->addDays($validated['days']);

        $tenant->update([
            'subscription_ends_at' => $newEndDate,
            'status' => 'active',
        ]);

        return back()->with('success', "Masa aktif tenant {$tenant->name} diperpanjang hingga {$newEndDate->format('d M Y')}.");
    }

    public function changePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $tenant->update(['plan_id' => $plan->id]);

        return back()->with('success', "Paket tenant {$tenant->name} berhasil diubah ke {$plan->name}.");
    }

    public function resetOwnerPassword(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        if (! $tenant->owner) {
            return back()->with('error', 'Tenant ini belum memiliki data owner.');
        }

        $tenant->owner->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', "Password owner {$tenant->owner->email} berhasil direset.");
    }
}
