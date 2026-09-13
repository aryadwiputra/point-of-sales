<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public const BUSINESS_TYPES = [
        'food' => ['food', 'beverages', 'snacks', 'coffeeTea'],
        'retail' => ['general', 'electronics', 'homeSupplies', 'beauty'],
        'grocery' => ['staples', 'produce', 'meatSeafood', 'snacks'],
        'fashion' => ['clothing', 'shoes', 'bags', 'accessories'],
        'pharmacy' => ['otcMedicine', 'prescriptionMedicine', 'vitamins', 'medicalSupplies'],
        'services' => ['services', 'products', 'packages'],
    ];

    public function index(): Response
    {
        $primaryWarehouse = Warehouse::find(Setting::get('setup_warehouse_id'));

        return Inertia::render('Setup/Wizard', [
            'businessTypes' => array_map(
                fn (string $key, array $categories) => ['key' => $key, 'categories' => $categories],
                array_keys(self::BUSINESS_TYPES),
                self::BUSINESS_TYPES,
            ),
            'primaryWarehouse' => $primaryWarehouse?->only(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_name' => 'required|string|max:255',
            'store_address' => 'nullable|string|max:500',
            'store_phone' => 'nullable|string|max:50',
            'store_email' => 'nullable|email|max:255',
            'store_logo' => 'nullable|image|max:2048',
            'business_type' => 'required|string|in:'.implode(',', array_keys(self::BUSINESS_TYPES)),
            'categories' => 'required|array|min:1',
            'categories.*' => 'required|string|max:255',
            'user_name' => 'required|string|max:255',
            'user_email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'warehouse_code' => [
                'required',
                'string',
                'max:20',
                function ($attribute, $value, $fail) use ($request) {
                    $warehouseId = $request->input('warehouse_id');
                    $exists = DB::table('warehouses')
                        ->where('code', $value)
                        ->when($warehouseId, fn ($q) => $q->where('id', '!=', $warehouseId))
                        ->exists();
                    if ($exists) {
                        $fail('Kode gudang sudah digunakan.');
                    }
                },
            ],
            'warehouse_name' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['user_name'],
                'email' => $validated['user_email'],
                'password' => Hash::make($validated['password']),
            ]);
            $user->assignRole('super-admin');

            if (! empty($validated['warehouse_id'])) {
                Warehouse::where('id', $validated['warehouse_id'])->update([
                    'code' => $validated['warehouse_code'],
                    'name' => $validated['warehouse_name'],
                ]);
                Setting::set('setup_warehouse_id', $validated['warehouse_id']);
            } else {
                $warehouse = Warehouse::create([
                    'code' => $validated['warehouse_code'],
                    'name' => $validated['warehouse_name'],
                    'type' => 'main',
                    'is_active' => true,
                    'sort_order' => 0,
                ]);
                Setting::set('setup_warehouse_id', $warehouse->id);
            }

            foreach (array_unique($validated['categories']) as $name) {
                Category::create(['name' => $name]);
            }

            foreach ([
                'store_name' => $validated['store_name'],
                'store_address' => $validated['store_address'] ?? '',
                'store_phone' => $validated['store_phone'] ?? '',
                'store_email' => $validated['store_email'] ?? '',
                'store_business_type' => $validated['business_type'],
            ] as $key => $value) {
                Setting::set($key, $value);
            }
            Setting::set('app_setup_completed', true, 'Penanda wizard setup awal sudah diselesaikan');
        });

        if ($request->file('store_logo')) {
            $path = $request->file('store_logo')->store('store', 'public');
            Setting::set('store_logo', $path, 'Logo toko');
        }

        return redirect()->route('login')->with('success', __('setup.completed'));
    }
}
