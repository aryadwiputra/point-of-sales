<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    protected array $reservedSubdomains = [
        'www',
        'localhost',
        '127.0.0.1',
        'admin',
        'superadmin',
        'api',
        'pos',
        'dikasir',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant) {
            TenantContext::set($tenant);
        }

        return $next($request);
    }

    protected function resolveTenant(Request $request): ?Tenant
    {
        if ($request->is('superadmin*')) {
            TenantContext::bypass();
            return null;
        }

        // 1. By Subdomain
        $host = $request->getHost();
        $parts = explode('.', $host);

        if (count($parts) >= 2) {
            $subdomain = strtolower($parts[0]);
            if (! in_array($subdomain, $this->reservedSubdomains, true) && ! is_numeric($subdomain)) {
                $tenant = Tenant::where('subdomain', $subdomain)->first();
                if ($tenant) {
                    return $tenant;
                }
            }
        }

        // 2. By Custom Domain
        $tenant = Tenant::where('custom_domain', $host)->first();
        if ($tenant) {
            return $tenant;
        }

        // 3. By Header (API clients)
        $headerId = $request->header('X-Tenant-ID');
        if ($headerId) {
            $tenant = Tenant::find($headerId);
            if ($tenant) {
                return $tenant;
            }
        }

        $headerSlug = $request->header('X-Tenant');
        if ($headerSlug) {
            $tenant = Tenant::where('subdomain', $headerSlug)->first();
            if ($tenant) {
                return $tenant;
            }
        }

        // 4. By Authenticated User
        if ($request->user() && $request->user()->tenant_id) {
            $tenant = Tenant::find($request->user()->tenant_id);
            if ($tenant) {
                return $tenant;
            }
        }

        // 5. By Session
        if ($request->hasSession() && $request->session()->has('current_tenant_id')) {
            $tenant = Tenant::find($request->session()->get('current_tenant_id'));
            if ($tenant) {
                return $tenant;
            }
        }

        return null;
    }
}
