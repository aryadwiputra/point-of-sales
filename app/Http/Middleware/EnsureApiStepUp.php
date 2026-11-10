<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API equivalent of the web step_up middleware (EnsureRecentPasswordConfirmation).
 *
 * Instead of the session, the confirmation window is tracked in cache per
 * Sanctum token so headless clients can re-authenticate via
 * POST /api/v1/auth/confirm-password before performing privileged actions.
 */
class EnsureApiStepUp
{
    public const CACHE_PREFIX = 'api_step_up:';

    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $cacheKey = $this->cacheKey($request);
        $confirmedAt = (int) cache()->get($cacheKey, 0);
        $timeout = (int) config('auth.password_timeout', 900);

        if ($confirmedAt > 0 && (time() - $confirmedAt) <= $timeout) {
            return $next($request);
        }

        $this->auditLogService->log(
            event: 'security.privileged_action_challenged',
            module: 'security',
            auditable: ['target_label' => $request->route()?->getName() ?? $request->path()],
            description: 'Aksi sensitif memerlukan konfirmasi password ulang.',
            meta: [
                'severity' => 'high',
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
            ],
        );

        return response()->json([
            'success' => false,
            'message' => 'Konfirmasi password diperlukan untuk aksi ini.',
            'step_up_required' => true,
        ], 423);
    }

    /**
     * Cache key scoped to the current access token, falling back to the user
     * id for transient tokens (e.g. Sanctum::actingAs in tests).
     */
    public static function cacheKey(Request $request): string
    {
        $token = $request->user()?->currentAccessToken();
        $tokenId = $token?->getKey();

        return self::CACHE_PREFIX.($tokenId ?: 'user:'.$request->user()?->getKey());
    }
}
