<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        Auth::logoutOtherDevices($validated['current_password']);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ]);
        $request->session()->regenerate();
        $request->session()->put('security.session_started_at', now()->timestamp);
        $request->session()->put('auth.password_confirmed_at', time());

        $this->auditLogService->log(
            event: 'auth.password_changed',
            module: 'auth',
            auditable: $request->user(),
            description: 'Password akun diganti.',
            meta: [
                'severity' => 'high',
                'route' => $request->route()?->getName(),
                'source' => 'profile',
            ],
        );

        return back();
    }
}
