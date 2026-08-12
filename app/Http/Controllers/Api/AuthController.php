<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\ApiResponder;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    /**
     * POST /api/v1/auth/login
     * Token-based login. Returns a Sanctum token with role-based abilities.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $key = 'api-login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $this->error('Terlalu banyak percobaan login. Coba lagi nanti.', 429);
        }

        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            RateLimiter::hit($key, 60);

            $this->auditLogService->log(
                event: 'api.auth.login_failed',
                module: 'auth',
                auditable: $user,
                description: 'Login API gagal.',
                meta: ['severity' => 'warning', 'ip' => $request->ip()],
            );

            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan tidak cocok dengan data kami.'],
            ]);
        }

        RateLimiter::clear($key);

        $this->auditLogService->log(
            event: 'api.auth.login_succeeded',
            module: 'auth',
            auditable: $user,
            description: 'Login API berhasil.',
            meta: ['severity' => 'info', 'ip' => $request->ip()],
        );

        // Token abilities derived from Spatie permissions
        $abilities = $user->getAllPermissions()->pluck('name')->unique()->values()->all();
        $abilities[] = 'user:read';

        $tokenName = $request->string('device_name')->toString() ?: 'api-'.str()->random(6);

        $token = $user->createToken($tokenName, $abilities)->plainTextToken;

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'email']),
            'roles' => $user->getRoleNames()->all(),
            'permissions' => $abilities,
        ], 'Login berhasil');
    }

    /**
     * POST /api/v1/auth/logout
     * Revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        $this->auditLogService->log(
            event: 'api.auth.logout',
            module: 'auth',
            auditable: $request->user(),
            description: 'Logout API.',
            meta: ['severity' => 'info', 'ip' => $request->ip()],
        );

        return $this->ok(null, 'Logout berhasil');
    }

    /**
     * GET /api/v1/auth/me
     * Current authenticated user with roles & permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->ok([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->all(),
            'store_profile' => [
                'name' => \App\Models\Setting::get('store_name', config('app.name')),
                'address' => \App\Models\Setting::get('store_address', ''),
                'phone' => \App\Models\Setting::get('store_phone', ''),
            ],
        ], 'OK');
    }

    /**
     * POST /api/v1/auth/register
     * Public registration (respects AUTH_PUBLIC_REGISTRATION config).
     */
    public function register(Request $request): JsonResponse
    {
        if (! config('security.auth.public_registration', false)) {
            return $this->forbidden('Pendaftaran publik sedang ditutup.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        // Default role: cashier
        $cashierRole = \Spatie\Permission\Models\Role::findByName('cashier', 'web');
        if ($cashierRole) {
            $user->assignRole($cashierRole);
        }

        $this->auditLogService->log(
            event: 'api.auth.register',
            module: 'auth',
            auditable: $user,
            description: 'Registrasi API.',
            meta: ['severity' => 'info', 'ip' => $request->ip()],
        );

        $token = $user->createToken('api-'.str()->random(6), ['user:read'])->plainTextToken;

        return $this->created([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'email']),
        ], 'Registrasi berhasil');
    }
}
