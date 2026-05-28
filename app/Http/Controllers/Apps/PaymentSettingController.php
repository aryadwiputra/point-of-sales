<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentSetting\UpdatePaymentSettingRequest;
use App\Services\PaymentSettings\PaymentSettingEditQueryService;
use App\Services\PaymentSettings\UpdatePaymentSettingService;
use Inertia\Inertia;

class PaymentSettingController extends Controller
{
    public function edit(PaymentSettingEditQueryService $service)
    {
        return Inertia::render('Dashboard/Settings/Payment', $service->execute());
    }

    public function update(UpdatePaymentSettingRequest $request, UpdatePaymentSettingService $service)
    {
        $service->execute($request->validated());

        return redirect()
            ->route('settings.payments.edit')
            ->with('success', 'Konfigurasi payment gateway berhasil disimpan.');
    }
}
