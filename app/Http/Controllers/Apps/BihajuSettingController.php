<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\BihajuBinding;
use App\Models\BihajuTransaction;
use App\Models\Outlet;
use App\Services\AddonService;
use App\Services\AuditLogService;
use App\Services\Bihaju\BihajuClient;
use App\Services\Bihaju\BihajuService;
use App\Support\AddonCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

/**
 * Pengaturan integrasi Bihaju per tenant: binding outlet, pencarian member,
 * dan riwayat pengiriman transaksi.
 */
class BihajuSettingController extends Controller
{
    public function __construct(
        private readonly AddonService $addons,
        private readonly BihajuService $bihaju,
        private readonly BihajuClient $client,
        private readonly AuditLogService $auditLog,
    ) {}

    public function index()
    {
        abort_unless($this->addons->isActive(AddonCodes::BIHAJU), 403,
            'Add-on Integrasi Bihaju belum aktif.');

        $summary = $this->bihaju->summary();

        $boundOutletIds = collect($summary['bindings'])->pluck('outlet_id')->filter()->all();

        $outlets = Outlet::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_active'])
            ->map(fn (Outlet $outlet) => [
                'id' => $outlet->id,
                'code' => $outlet->code,
                'name' => $outlet->name,
                'is_active' => $outlet->is_active,
                'bound' => in_array($outlet->id, $boundOutletIds, true),
            ])
            ->values();

        return Inertia::render('Dashboard/Settings/Bihaju', [
            'summary' => $summary,
            'outlets' => $outlets,
        ]);
    }

    /**
     * Buat permohonan binding (QR) untuk satu outlet.
     */
    public function bind(Request $request): JsonResponse
    {
        abort_unless($this->addons->isActive(AddonCodes::BIHAJU), 403,
            'Add-on Integrasi Bihaju belum aktif.');

        $request->validate([
            'outlet_id' => ['required', 'integer', 'exists:outlets,id'],
        ]);

        $outlet = Outlet::find($request->integer('outlet_id'));

        abort_unless($outlet, 404);

        $existing = BihajuBinding::query()
            ->where('outlet_id', $outlet->id)
            ->whereIn('status', [BihajuBinding::STATUS_PENDING, BihajuBinding::STATUS_ACTIVE])
            ->first();

        if ($existing && $existing->isActive()) {
            return response()->json([
                'message' => 'Outlet ini sudah terhubung ke Bihaju.',
                'binding' => $existing->toClientArray(),
            ], 409);
        }

        // Satu external_ref per outlet, dipakai ulang untuk permohonan berikutnya.
        $externalRef = $existing?->external_ref
            ?? sprintf('bpos-%d-outlet-%d', (int) $outlet->tenant_id, $outlet->id);

        try {
            $qr = $this->client->createBindingQr($externalRef);
        } catch (Throwable $exception) {
            Log::warning('Gagal membuat QR binding Bihaju.', ['error' => $exception->getMessage()]);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }

        $binding = $existing ?: new BihajuBinding([
            'tenant_id' => $outlet->tenant_id,
            'outlet_id' => $outlet->id,
            'external_ref' => $externalRef,
        ]);

        $binding->forceFill([
            'status' => BihajuBinding::STATUS_PENDING,
            'bind_code' => $qr['code'],
            'bind_url' => $qr['url'],
            'bind_code_expires_at' => isset($qr['expires_at']) && $qr['expires_at']
                ? \Illuminate\Support\Carbon::parse($qr['expires_at'])
                : now()->addMinutes(10),
            'revoked_at' => null,
        ])->save();

        $this->auditLog->log(
            'bind',
            'integrations',
            $binding,
            'Permohonan binding Bihaju dibuat untuk outlet '.$outlet->name,
        );

        return response()->json([
            'message' => 'Scan QR ini di aplikasi Bihaju.',
            'binding' => $binding->fresh()->toClientArray(),
            'bind_url' => $qr['url'],
        ], 201);
    }

    /**
     * Putuskan binding secara lokal (merchant juga perlu mencabut di Bihaju).
     */
    public function unbind(BihajuBinding $binding): JsonResponse
    {
        abort_unless($this->addons->isActive(AddonCodes::BIHAJU), 403,
            'Add-on Integrasi Bihaju belum aktif.');

        $binding->forceFill([
            'status' => BihajuBinding::STATUS_REVOKED,
            'merchant_api_key' => null,
            'bind_code' => null,
            'bind_url' => null,
            'bind_code_expires_at' => null,
            'revoked_at' => now(),
        ])->save();

        $this->auditLog->log(
            'unbind',
            'integrations',
            $binding,
            'Binding Bihaju diputus dari sisi BPOS',
        );

        return response()->json([
            'message' => 'Outlet diputus dari Bihaju.',
            'binding' => $binding->fresh()->toClientArray(),
        ]);
    }

    /**
     * Proxy pencarian member Bihaju (min. 3 karakter).
     */
    public function members(Request $request): JsonResponse
    {
        abort_unless($this->addons->isActive(AddonCodes::BIHAJU), 403,
            'Add-on Integrasi Bihaju belum aktif.');

        $request->validate([
            'binding_id' => ['required', 'integer', 'exists:bihaju_bindings,id'],
            'q' => ['required', 'string', 'min:3', 'max:100'],
        ]);

        $binding = BihajuBinding::find($request->integer('binding_id'));

        abort_unless($binding && $binding->isActive(), 409, 'Outlet belum terhubung ke Bihaju.');

        try {
            $members = $this->client->searchMembers($binding, $request->input('q'));
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json(['data' => $members]);
    }

    /**
     * Coba ulang pengiriman transaksi yang gagal/tertunda.
     */
    public function retry(BihajuTransaction $bihajuTransaction): JsonResponse
    {
        abort_unless($this->addons->isActive(AddonCodes::BIHAJU), 403,
            'Add-on Integrasi Bihaju belum aktif.');

        if ($bihajuTransaction->status === BihajuTransaction::STATUS_SUCCEEDED) {
            return response()->json(['message' => 'Transaksi sudah terkirim.']);
        }

        $this->bihaju->attempt($bihajuTransaction);

        return response()->json([
            'message' => 'Transaksi dikirim ulang.',
            'transaction' => $bihajuTransaction->fresh()->toClientArray(),
        ]);
    }
}
