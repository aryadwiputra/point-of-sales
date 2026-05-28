<?php

declare(strict_types=1);

namespace App\Services\Queries;

use Illuminate\Support\Collection;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Village;

class RegionQueryService
{
    public function regencies(string $provinceCode): Collection
    {
        return City::query()
            ->where('province_code', $provinceCode)
            ->select('code', 'name')
            ->orderBy('name')
            ->get();
    }

    public function districts(string $cityCode): Collection
    {
        return District::query()
            ->where('city_code', $cityCode)
            ->select('code', 'name')
            ->orderBy('name')
            ->get();
    }

    public function villages(string $districtCode): Collection
    {
        return Village::query()
            ->where('district_code', $districtCode)
            ->select('code', 'name')
            ->orderBy('name')
            ->get();
    }
}
