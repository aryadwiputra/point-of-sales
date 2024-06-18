<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::create([
            'name' => 'Arya Dwi Putra',
            'email' => 'arya@gmail.com',
            'password' => bcrypt('password'),
        ]);

        // get admin role
        $role = Role::where('name', 'super-admin')->first();

        // get all permissions
        $permissions = Permission::all();

        // assign role to user
        $user->syncPermissions($permissions);

        // assign a role to user
        $user->assignRole($role);

        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@gmail.com',
            'password' => bcrypt('password'),
        ]);

        $transactionsPermission = Permission::where('name', 'transactions-access')->first();

        $cashier->syncPermissions($transactionsPermission);
    }
}
