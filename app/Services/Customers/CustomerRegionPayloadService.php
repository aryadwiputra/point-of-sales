<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use Illuminate\Support\Collection;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\Village;

class CustomerRegionPayloadService
{
    public function provinces(): Collection
    {
        return Province::query()
            ->select('code', 'name')
            ->orderBy('name')
            ->get();
    }

    public function selectedOptions(Customer $customer): array
    {
        return [
            'regencies' => $customer->province_id
                ? $this->regencies($customer->province_id)
                : [],
            'districts' => $customer->regency_id
                ? $this->districts($customer->regency_id)
                : [],
            'villages' => $customer->district_id
                ? $this->villages($customer->district_id)
                : [],
        ];
    }

    public function resolvePayload(array $data): array
    {
        $provinceCode = $data['province_id'] ?? null;
        $regencyCode = $data['regency_id'] ?? null;
        $districtCode = $data['district_id'] ?? null;
        $villageCode = $data['village_id'] ?? null;

        $province = $provinceCode ? Province::query()->where('code', $provinceCode)->first() : null;
        $regency = $regencyCode ? City::query()->where('code', $regencyCode)->first() : null;
        $district = $districtCode ? District::query()->where('code', $districtCode)->first() : null;
        $village = $villageCode ? Village::query()->where('code', $villageCode)->first() : null;

        return [
            'province_id' => $provinceCode,
            'province_name' => $province?->name,
            'regency_id' => $regencyCode,
            'regency_name' => $regency?->name,
            'district_id' => $districtCode,
            'district_name' => $district?->name,
            'village_id' => $villageCode,
            'village_name' => $village?->name,
        ];
    }

    private function regencies(string $provinceCode): Collection
    {
        return City::query()
            ->where('province_code', $provinceCode)
            ->select('code', 'name')
            ->orderBy('name')
            ->get();
    }

    private function districts(string $regencyCode): Collection
    {
        return District::query()
            ->where('city_code', $regencyCode)
            ->select('code', 'name')
            ->orderBy('name')
            ->get();
    }

    private function villages(string $districtCode): Collection
    {
        return Village::query()
            ->where('district_code', $districtCode)
            ->select('code', 'name')
            ->orderBy('name')
            ->get();
    }
}
