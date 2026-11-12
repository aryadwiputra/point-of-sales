<?php

namespace App\Http\Middleware;

use App\Services\QuotaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanQuota
{
    public function __construct(protected QuotaService $quotaService)
    {
    }

    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $allowed = match ($resource) {
            'outlet' => $this->quotaService->canCreateOutlet(),
            'user' => $this->quotaService->canAddUser(),
            'product' => $this->quotaService->canCreateProduct(),
            'customer' => $this->quotaService->canAddCustomer(),
            default => true,
        };

        if (! $allowed) {
            $plan = $this->quotaService->getPlan();
            $max = match ($resource) {
                'outlet' => $plan?->max_outlets,
                'user' => $plan?->max_users,
                'product' => $plan?->max_products,
                'customer' => $plan?->max_customers,
                default => null,
            };

            $message = "Kuota {$resource} telah mencapai batas maksimum paket langganan Anda ({$max}). Silakan upgrade paket.";

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => $message,
                    'error' => 'quota_exceeded',
                    'resource' => $resource,
                    'max' => $max,
                ], Response::HTTP_FORBIDDEN);
            }

            return back()->with('error', $message);
        }

        return $next($request);
    }
}
