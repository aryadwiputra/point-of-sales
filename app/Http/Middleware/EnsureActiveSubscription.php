<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = TenantContext::getId();

        if (! $tenantId || TenantContext::isBypassed() || $request->user()?->isPlatformSuperAdmin()) {
            return $next($request);
        }

        // Read a fresh row: the cached context model may predate a status change.
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return $next($request);
        }

        if (in_array($tenant->status, ['suspended', 'cancelled'], true)) {
            abort(Response::HTTP_FORBIDDEN, 'Akun toko Anda sedang '.$tenant->status.'. Hubungi admin platform untuk mengaktifkan kembali.');
        }

        if (! $tenant->isActive()) {
            if ($tenant->status !== 'expired') {
                $tenant->update(['status' => 'expired']);
            }

            // The subscription page stays reachable so the tenant can renew.
            if (! $request->routeIs('subscription.*')) {
                return redirect()
                    ->route('subscription.index')
                    ->with('error', 'Masa aktif langganan Anda telah berakhir. Silakan pilih paket untuk memperpanjang.');
            }
        }

        return $next($request);
    }
}
