<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateLoyaltySettingRequest;
use App\Http\Requests\Setting\UpdateStoreProfileSettingRequest;
use App\Http\Requests\Setting\UpdateTargetSettingRequest;
use App\Services\Settings\LoyaltySettingQueryService;
use App\Services\Settings\StoreProfileSettingQueryService;
use App\Services\Settings\TargetSettingQueryService;
use App\Services\Settings\UpdateLoyaltySettingService;
use App\Services\Settings\UpdateStoreProfileSettingService;
use App\Services\Settings\UpdateTargetSettingService;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function target(TargetSettingQueryService $service)
    {
        return Inertia::render('Dashboard/Settings/Target', $service->execute());
    }

    public function updateTarget(UpdateTargetSettingRequest $request, UpdateTargetSettingService $service)
    {
        $service->execute($request->validated());

        return back()->with('success', 'Target berhasil disimpan');
    }

    public function storeProfile(StoreProfileSettingQueryService $service)
    {
        return Inertia::render('Dashboard/Settings/Store', $service->execute());
    }

    public function updateStoreProfile(
        UpdateStoreProfileSettingRequest $request,
        UpdateStoreProfileSettingService $service
    ) {
        $service->execute($request->validated());

        return back()->with('success', 'Profil toko berhasil diperbarui');
    }

    public function loyalty(LoyaltySettingQueryService $service)
    {
        return Inertia::render('Dashboard/Settings/Loyalty', $service->execute());
    }

    public function updateLoyalty(UpdateLoyaltySettingRequest $request, UpdateLoyaltySettingService $service)
    {
        $service->execute($request->payload());

        return back()->with('success', 'Pengaturan loyalty berhasil disimpan');
    }
}
