<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Payable;
use App\Models\Receivable;
use App\Models\Setting;
use App\Models\Transaction;

class DocumentPayloadQueryService
{
    public function transaction(string $invoice): Transaction
    {
        return Transaction::query()
            ->with(['details.product', 'cashier', 'customer'])
            ->where('invoice', $invoice)
            ->firstOrFail();
    }

    public function receivable(Receivable $receivable): Receivable
    {
        return $receivable->load(['customer', 'payments.bankAccount', 'payments.user']);
    }

    public function payable(Payable $payable): Payable
    {
        return $payable->load(['supplier', 'payments.bankAccount', 'payments.user']);
    }

    public function storeProfile(): array
    {
        $logo = $this->normalizeLogo(Setting::get('store_logo'));

        return [
            'name' => Setting::get('store_name', 'Toko Anda'),
            'logo' => $logo,
            'logo_data' => $this->embeddedLogo($logo),
            'address' => Setting::get('store_address', ''),
            'phone' => Setting::get('store_phone', ''),
            'email' => Setting::get('store_email', ''),
            'website' => Setting::get('store_website', ''),
        ];
    }

    private function normalizeLogo(?string $logo): ?string
    {
        if ($logo && ! str_starts_with($logo, 'http') && ! str_starts_with($logo, '/storage')) {
            return asset('storage/'.ltrim($logo, '/'));
        }

        return $logo;
    }

    private function embeddedLogo(?string $logo): ?string
    {
        if (! $logo) {
            return null;
        }

        $localPath = null;
        if (str_starts_with($logo, asset('storage'))) {
            $localPath = public_path(str_replace(asset(''), '', $logo));
        } elseif (str_starts_with($logo, '/storage')) {
            $localPath = public_path($logo);
        }

        if (! $localPath || ! file_exists($localPath)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($localPath));
    }
}
