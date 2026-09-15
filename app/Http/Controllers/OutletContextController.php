<?php

namespace App\Http\Controllers;

use App\Services\OutletAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OutletContextController extends Controller
{
    public function __construct(private readonly OutletAccessService $outletAccess) {}

    public function switch(Request $request): RedirectResponse
    {
        $outletId = $request->validate(['outlet_id' => ['required', 'integer']])['outlet_id'];
        $outlet = $this->outletAccess->accessibleOutlets($request->user())->firstWhere('id', (int) $outletId);

        abort_unless($outlet, 404);
        abort_if($this->outletAccess->hasActiveShiftInOtherOutlet($request->user(), $outlet), 422, 'Tutup shift aktif sebelum mengganti outlet.');

        $request->session()->put('active_outlet_id', $outlet->id);

        return back();
    }
}
