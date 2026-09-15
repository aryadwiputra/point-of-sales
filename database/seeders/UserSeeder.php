<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::updateOrCreate(
            ['email' => 'arya@gmail.com'],
            [
                'name' => 'Arya Dwi Putra',
                'password' => Hash::make('password'),
            ]
        );

        $superAdminRole = Role::where('name', 'super-admin')->first();
        $permissions = Permission::all();

        if ($superAdminRole) {
            $admin->syncRoles([$superAdminRole->name]);
        }

        $admin->syncPermissions($permissions);
        // Demo accounts skip email verification (dashboard is guarded by 'verified' middleware).
        $admin->markEmailAsVerified();

        $outlets = Outlet::whereIn('code', ['MAL', 'TKB', 'PUT'])->get();
        if ($outlets->isNotEmpty()) {
            $admin->outlets()->sync($outlets->mapWithKeys(fn (Outlet $outlet) => [
                $outlet->id => ['is_default' => $outlet->code === 'MAL'],
            ])->all());
        }

        $cashier = User::updateOrCreate(
            ['email' => 'cashier@gmail.com'],
            [
                'name' => 'Cashier',
                'password' => Hash::make('password'),
            ]
        );

        $cashierRole = Role::where('name', 'cashier')->first();
        $cashier->markEmailAsVerified();

        $mal = $outlets->firstWhere('code', 'MAL');
        if ($mal) {
            $cashier->outlets()->sync([$mal->id => ['is_default' => true]]);
        }

        if ($cashierRole) {
            $cashier->syncRoles([$cashierRole->name]);
            $cashier->syncPermissions([]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        $transactionsPermission = Permission::where('name', 'transactions-access')->first();
        $cashier->syncPermissions($transactionsPermission ? [$transactionsPermission] : []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
