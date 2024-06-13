<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // get all permissions data where name like users
        $user_permissions = Permission::where('name', 'like', '%users%')->get();

        // create new role
        $user_group = Role::create(['name' => 'users-access']);

        // assign a permissions to a access role
        $user_group->givePermissionTo($user_permissions);

        // get all permissions data where name like roles
        $role_permissions = Permission::where('name', 'like', '%roles%')->get();

        // create new role
        $role_group = Role::create(['name' => 'roles-access']);

        // assign a permissions to a role
        $role_group->givePermissionTo($role_permissions);

        //  get all permissions data where name like permissions
        $permission_permissions = Permission::where('name', 'like', '%permissions%')->get();

        // create new role
        $permission_group = Role::create(['name' => 'permission-access']);

        // assign a permissions to a role
        $permission_group->givePermissionTo($permission_permissions);

        // create new role
        Role::create(['name' => 'super-admin']);
    }
}
