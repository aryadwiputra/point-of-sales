<?php

namespace App\Http\Middleware;

use App\Services\AddonService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureAccess
{
    public function __construct(protected AddonService $addons)
    {
    }

    /**
     * @param  string  $feature  nama fitur paket (mis. 'crm') atau kode add-on ('finance', 'bihaju')
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->addons->isActive($feature)) {
            $message = "Fitur '{$feature}' tidak tersedia pada paket langganan Anda. Silakan upgrade paket.";

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => $message,
                    'error' => 'feature_not_available',
                    'feature' => $feature,
                ], Response::HTTP_FORBIDDEN);
            }

            return back()->with('error', $message);
        }

        return $next($request);
    }
}
