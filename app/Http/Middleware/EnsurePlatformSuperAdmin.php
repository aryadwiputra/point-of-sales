<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isPlatformSuperAdmin()) {
            abort(Response::HTTP_FORBIDDEN, 'Akses khusus Platform Superadmin.');
        }

        TenantContext::bypass();

        return $next($request);
    }
}
