<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Services\AddonService;
use App\Services\AuditLogService;
use App\Services\FinanceSsoService;
use App\Support\AddonCodes;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Hub integrasi & add-on tenant (Finance, Bihaju).
 */
class IntegrationController extends Controller
{
    public function __construct(
        private readonly AddonService $addons,
        private readonly FinanceSsoService $financeSso,
        private readonly AuditLogService $auditLog,
    ) {}

    public function index()
    {
        return Inertia::render('Dashboard/Settings/Integrations', [
            'addons' => $this->addons->status(),
            'financeUrl' => config('finance.url'),
        ]);
    }

    public function update(Request $request, string $code)
    {
        abort_unless(AddonCodes::isValid($code), 404);

        $request->validate([
            'action' => 'required|in:activate,deactivate',
            'expires_at' => 'nullable|date|after:today',
            'config' => 'nullable|array',
        ]);

        if ($request->input('action') === 'activate') {
            $this->addons->activate($code, $request->input('config', []), $request->filled('expires_at')
                ? \Illuminate\Support\Carbon::parse($request->input('expires_at'))->endOfDay()
                : null);

            $message = AddonCodes::label($code).' diaktifkan.';
        } else {
            $this->addons->deactivate($code);
            $message = AddonCodes::label($code).' dinonaktifkan.';
        }

        $this->auditLog->log(
            'addon.'.$request->input('action'),
            'integrations',
            null,
            AddonCodes::label($code).' → '.$request->input('action'),
            null,
            null,
            ['code' => $code, 'config_keys' => array_keys($request->input('config', []))],
        );

        return back()->with('success', $message);
    }

    /**
     * Peluncuran aplikasi Finance dengan token SSO sekali pakai.
     *
     * Entitlement divalidasi per tenant/akun (plan features / tenant_addons aktif)
     * dan token SSO diverifikasi ulang oleh aplikasi Finance pada setiap request.
     */
    public function launchFinance()
    {
        if (! $this->addons->isActive(AddonCodes::FINANCE)) {
            return redirect()->route('settings.integrations')->with(
                'error',
                'Add-on Finance belum aktif untuk akun/tenant ini. Silakan beli atau aktifkan add-on Finance terlebih dahulu.'
            );
        }

        $url = $this->financeSso->launchUrl(
            $this->addons->getTenant(),
            request()->user(),
        );

        $this->auditLog->log(
            'launch',
            'integrations',
            null,
            'Membuka aplikasi Finance via SSO',
            null,
            null,
            ['tenant_id' => $this->addons->getTenant()?->id, 'user_id' => request()->user()?->id],
        );

        return redirect()->away($url);
    }
}
