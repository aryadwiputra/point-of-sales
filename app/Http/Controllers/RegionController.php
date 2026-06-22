<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Region\GetDistrictsRequest;
use App\Http\Requests\Region\GetRegenciesRequest;
use App\Http\Requests\Region\GetVillagesRequest;
use App\Services\Queries\RegionQueryService;

class RegionController extends Controller
{
    public function regencies(GetRegenciesRequest $request, RegionQueryService $service)
    {
        return $service->regencies($request->validated('province_id'));
    }

    public function districts(GetDistrictsRequest $request, RegionQueryService $service)
    {
        return $service->districts($request->validated('regency_id'));
    }

    public function villages(GetVillagesRequest $request, RegionQueryService $service)
    {
        return $service->villages($request->validated('district_id'));
    }
}
