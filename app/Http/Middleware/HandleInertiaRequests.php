<?php

namespace App\Http\Middleware;

use App\Models\CashierShift;
use App\Models\Payable;
use App\Models\Product;
use App\Models\Receivable;
use App\Services\CashierShiftService;
use App\Services\PayableAgingService;
use App\Services\ReceivableService;
use App\Support\ProductionSecurityBaseline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $lowStockNotifications = [];
        $receivableNotifications = [];
        $payableNotifications = [];
        $activeCashierShift = null;
        $securityWarnings = [];
        $stepUpFreshUntil = null;
        $payableAgingSummary = null;
        $receivableAgingSummary = null;

        if ($request->user()) {
            $userId = $request->user()->id;

            $lowStockNotifications = Product::where('min_stock', '>', 0)
                ->whereColumn('stock', '<=', 'min_stock')
                ->whereNotExists(function ($query) use ($userId) {
                    $query->selectRaw('1')
                        ->from('product_notification_reads as pr')
                        ->whereColumn('pr.product_id', 'products.id')
                        ->where('pr.user_id', $userId)
                        ->whereColumn('pr.updated_at', '>=', 'products.updated_at');
                })
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get(['id', 'title', 'stock', 'updated_at'])
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'title' => $product->title,
                        'stock' => (int) $product->stock,
                        'time' => optional($product->updated_at)->diffForHumans(),
                    ];
                });

            $payableAgingService = new PayableAgingService;
            $receivableService = new ReceivableService;

            $payableAgingSummary = $payableAgingService->getAgingSummary();
            $receivableAgingSummary = $receivableService->getAgingSummary();

            $receivableNotifications = Receivable::whereNot('status', 'paid')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<=', now()->addDays(3))
                ->orderBy('due_date')
                ->limit(5)
                ->get(['id', 'invoice', 'customer_id', 'due_date', 'total', 'paid', 'status'])
                ->map(function ($item) {
                    $remaining = max(0, ($item->total ?? 0) - ($item->paid ?? 0));

                    return [
                        'id' => $item->id,
                        'title' => "Piutang: {$item->invoice}",
                        'subtitle' => 'Sisa '.number_format($remaining, 0, ',', '.'),
                        'time' => optional($item->due_date)->diffForHumans(),
                        'status' => $item->status,
                        'aging_bucket' => $item->aging_bucket,
                    ];
                });

            $payableNotifications = Payable::whereNot('status', 'paid')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<=', now()->addDays(3))
                ->orderBy('due_date')
                ->limit(5)
                ->get(['id', 'document_number', 'due_date', 'total', 'paid', 'status'])
                ->map(function ($item) {
                    $remaining = max(0, ($item->total ?? 0) - ($item->paid ?? 0));

                    return [
                        'id' => $item->id,
                        'title' => "Hutang: {$item->document_number}",
                        'subtitle' => 'Sisa '.number_format($remaining, 0, ',', '.'),
                        'time' => optional($item->due_date)->diffForHumans(),
                        'status' => $item->status,
                        'aging_bucket' => $item->aging_bucket,
                    ];
                });

            $activeShift = CashierShift::query()
                ->with('user:id,name', 'warehouse:id,code,name')
                ->open()
                ->where('user_id', $userId)
                ->latest('opened_at')
                ->first();

            if ($activeShift) {
                $activeCashierShift = app(CashierShiftService::class)->summarizeForDisplay($activeShift);
            }

            $securityWarnings = ProductionSecurityBaseline::issues();

            $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);
            if ($confirmedAt > 0) {
                $stepUpFreshUntil = now()
                    ->setTimestamp($confirmedAt + (int) config('auth.password_timeout', 900))
                    ->toISOString();
            }
        }

        $storeProfile = [
            'name' => 'Toko Anda',
            'logo' => null,
            'address' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'city' => '',
        ];

        if (Schema::hasTable('settings')) {
            $logo = \App\Models\Setting::get('store_logo');
            if ($logo && ! str_starts_with($logo, 'http') && ! str_starts_with($logo, '/storage')) {
                $logo = asset('storage/'.ltrim($logo, '/'));
            }

            $storeProfile = [
                'name' => \App\Models\Setting::get('store_name', 'Toko Anda'),
                'logo' => $logo,
                'address' => \App\Models\Setting::get('store_address', ''),
                'phone' => \App\Models\Setting::get('store_phone', ''),
                'email' => \App\Models\Setting::get('store_email', ''),
                'website' => \App\Models\Setting::get('store_website', ''),
                'city' => \App\Models\Setting::get('store_city', ''),
            ];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user() ? $request->user()->getPermissions() : [],
                'super' => $request->user() ? $request->user()->isSuperAdmin() : false,
            ],
            'lowStockNotifications' => $lowStockNotifications,
            'receivableNotifications' => $receivableNotifications,
            'payableNotifications' => $payableNotifications,
            'payableAgingSummary' => $payableAgingSummary,
            'receivableAgingSummary' => $receivableAgingSummary,
            'activeCashierShift' => $activeCashierShift,
            'storeProfile' => $storeProfile,
            'security' => [
                'warnings' => $securityWarnings,
                'publicRegistrationEnabled' => config('security.auth.public_registration'),
                'stepUpFreshUntil' => $stepUpFreshUntil,
            ],
        ];
    }
}
