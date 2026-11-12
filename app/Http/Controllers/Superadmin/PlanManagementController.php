<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanManagementController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::withCount('tenants')->get();

        return Inertia::render('Superadmin/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'max_outlets' => 'required|integer|min:1',
            'max_users' => 'required|integer|min:1',
            'max_products' => 'required|integer|min:1',
            'max_customers' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            // Billing & biaya transaksi (tier pay-per-transaksi).
            'billing_type' => 'nullable|in:subscription,per_transaction',
            'fee_type' => 'nullable|in:percent,fixed',
            'fee_value' => 'nullable|numeric|min:0',
            'fee_min' => 'nullable|numeric|min:0',
            'fee_max' => 'nullable|numeric|min:0',
            'fee_rounding' => 'nullable|in:none,up,down',
            'fee_methods' => 'nullable|array',
            'fee_methods.*' => 'string|in:cash,bank_transfer,midtrans,xendit,qris,pay_later,split',
        ]);

        // Paket langganan biasa tidak menyimpan konfigurasi biaya transaksi.
        if (($validated['billing_type'] ?? $plan->billing_type) !== 'per_transaction') {
            $validated = array_merge($validated, [
                'fee_type' => null,
                'fee_value' => 0,
                'fee_min' => 0,
                'fee_max' => null,
                'fee_rounding' => 'none',
                'fee_methods' => null,
            ]);
        }

        // fee_value untuk tipe persentase maksimal 100%.
        if (($validated['fee_type'] ?? null) === 'percent' && (float) ($validated['fee_value'] ?? 0) > 100) {
            $validated['fee_value'] = 100;
        }

        $plan->update($validated);

        return back()->with('success', "Paket {$plan->name} berhasil diperbarui.");
    }
}
