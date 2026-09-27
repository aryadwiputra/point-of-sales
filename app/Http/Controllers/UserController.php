<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Outlet;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // get all users data
        $users = User::query()
            ->with('roles')
            ->when(request()->search, fn ($query) => $query->where('name', 'like', '%'.request()->search.'%'))
            ->select('id', 'name', 'avatar', 'email')
            ->latest()
            ->paginate(7)
            ->withQueryString();

        // render view
        return Inertia::render('Dashboard/Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // get all role data
        $roles = Role::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // render view
        return Inertia::render('Dashboard/Users/Create', [
            'roles' => $roles,
            'outlets' => Outlet::active()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserRequest $request)
    {
        $avatarPath = null;

        if ($request->file('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        // create new user data
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'avatar' => $avatarPath,
        ]);

        // assign role to user
        $user->assignRole($request->selectedRoles);
        $user->outlets()->sync($this->outletAssignments($request));

        $this->auditLogService->log(
            event: 'user.created',
            module: 'users',
            auditable: $user,
            description: 'Pengguna baru dibuat.',
            after: $this->userPayload(
                $user,
                $this->auditLogService->roleNames($request->selectedRoles),
                $avatarPath !== null
            ),
        );

        // render view
        return to_route('users.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        // get all role data
        $roles = Role::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // load relationship
        $user->load(['roles' => fn ($query) => $query->select('id', 'name'), 'roles.permissions' => fn ($query) => $query->select('id', 'name'), 'outlets']);

        // render view
        return Inertia::render('Dashboard/Users/Edit', [
            'roles' => $roles,
            'user' => $user,
            'outlets' => Outlet::active()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserRequest $request, User $user)
    {
        $beforeRoles = $user->roles()->pluck('name')->all();
        $before = $this->userPayload($user, $beforeRoles, false);
        $avatarPath = $user->getRawOriginal('avatar');
        $avatarChanged = false;

        if ($request->file('avatar')) {
            if ($avatarPath) {
                Storage::disk('public')->delete($avatarPath);
            }

            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $avatarChanged = true;
        }

        // check if user send request password
        if ($request->password) {
            // update user data password
            $user->update([
                'password' => bcrypt($request->password),
            ]);
        }

        // update user data name
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'avatar' => $avatarPath,
        ]);

        // assign role to user
        $user->syncRoles($request->selectedRoles);
        $user->outlets()->sync($this->outletAssignments($request));

        $afterRoles = $this->auditLogService->roleNames($request->selectedRoles);
        $after = $this->userPayload($user->fresh(), $afterRoles, $avatarChanged);

        $this->auditLogService->log(
            event: 'user.updated',
            module: 'users',
            auditable: $user,
            description: 'Data pengguna diperbarui.',
            before: $before,
            after: $after,
        );

        if ($beforeRoles !== $afterRoles) {
            $this->auditLogService->log(
                event: 'user.role_changed',
                module: 'users',
                auditable: $user,
                description: 'Role pengguna diperbarui.',
                before: ['roles' => array_values($beforeRoles)],
                after: ['roles' => array_values($afterRoles)],
            );
        }

        // render view
        return to_route('users.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $ids = collect(explode(',', (string) $id))
            ->map(fn ($value) => (int) trim($value))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Tidak ada pengguna yang dipilih untuk dihapus.');
        }

        // Prevent deleting your own account (avoids accidental self lockout).
        if ($ids->contains(auth()->id())) {
            $this->auditLogService->log(
                event: 'user.delete_blocked',
                module: 'users',
                auditable: null,
                description: 'Penghapusan akun sendiri diblokir.',
                meta: ['severity' => 'warning', 'ids' => $ids->all()],
            );

            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $users = User::query()->with('roles')->whereIn('id', $ids)->get();

        // Prevent removing the last remaining super-admin (avoids system lockout).
        $superAdminIds = $users
            ->filter(fn (User $user) => $user->hasRole('super-admin'))
            ->pluck('id');

        if ($superAdminIds->isNotEmpty()) {
            $remainingSuperAdmins = User::query()
                ->role('super-admin')
                ->whereNotIn('id', $superAdminIds)
                ->count();

            if ($remainingSuperAdmins === 0) {
                $this->auditLogService->log(
                    event: 'user.delete_blocked',
                    module: 'users',
                    auditable: null,
                    description: 'Penghapusan super-admin terakhir diblokir.',
                    meta: ['severity' => 'critical', 'ids' => $superAdminIds->all()],
                );

                return back()->with('error', 'Super-admin terakhir tidak dapat dihapus.');
            }
        }

        DB::transaction(function () use ($users) {
            foreach ($users as $user) {
                $this->auditLogService->log(
                    event: 'user.deleted',
                    module: 'users',
                    auditable: $user,
                    description: 'Pengguna dihapus.',
                    before: $this->userPayload($user, $user->roles->pluck('name')->all(), false),
                );
            }

            User::whereIn('id', $users->pluck('id'))->delete();
        });

        // render view
        return back();
    }

    private function userPayload(User $user, array $roles, bool $avatarChanged): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'avatar_changed' => $avatarChanged,
            'roles' => array_values($roles),
        ];
    }

    private function outletAssignments(UserRequest $request): array
    {
        $ids = collect($request->input('outlet_ids', []))->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $default = (int) $request->input('default_outlet_id');

        return $ids->mapWithKeys(fn ($id) => [$id => ['is_default' => $id === $default]])->all();
    }
}
