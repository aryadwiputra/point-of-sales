<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\UserController as RootUserController;
use App\Http\Traits\ApiResponder;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class UserController extends RootUserController
{
    use ApiResponder;

    /**
     * GET /api/v1/users
     */
    public function listUsers(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('roles:id,name')
            ->when($request->input('search'), fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->select('id', 'name', 'avatar', 'email')
            ->latest()
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->paginated($users);
    }

    /**
     * GET /api/v1/users/create
     */
    public function createData(): JsonResponse
    {
        return $this->ok([
            'roles' => Role::query()->select('id', 'name')->orderBy('name')->get(),
            'outlets' => Outlet::active()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * POST /api/v1/users
     */
    public function storeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'selectedRoles' => ['required', 'array'],
            'selectedRoles.*' => ['string', 'exists:roles,name'],
            'outlet_ids' => ['nullable', 'array'],
            'outlet_ids.*' => ['integer', 'exists:outlets,id'],
            'default_outlet_id' => ['nullable', 'integer', 'exists:outlets,id'],
        ]);

        $avatarPath = ($validated['avatar'] ?? null) ? $request->file('avatar')->store('avatars', 'public') : null;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'avatar' => $avatarPath,
        ]);
        $user->assignRole($validated['selectedRoles']);
        $user->outlets()->sync($this->outletAssignments($request));

        $this->auditLogService->log(
            event: 'user.created',
            module: 'users',
            auditable: $user,
            description: 'Pengguna baru dibuat.',
            after: $this->userPayload($user, $this->auditLogService->roleNames($validated['selectedRoles']), $avatarPath !== null),
        );

        return $this->created($user->load('roles:id,name'));
    }

    /**
     * GET /api/v1/users/{user}
     */
    public function editData(User $user): JsonResponse
    {
        $user->load([
            'roles' => fn ($query) => $query->select('id', 'name'),
            'roles.permissions' => fn ($query) => $query->select('id', 'name'),
            'outlets',
        ]);

        return $this->ok([
            'user' => $user,
            'roles' => Role::query()->select('id', 'name')->orderBy('name')->get(),
            'outlets' => Outlet::active()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * PUT /api/v1/users/{user}
     */
    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'selectedRoles' => ['required', 'array'],
            'selectedRoles.*' => ['string', 'exists:roles,name'],
            'outlet_ids' => ['nullable', 'array'],
            'outlet_ids.*' => ['integer', 'exists:outlets,id'],
            'default_outlet_id' => ['nullable', 'integer', 'exists:outlets,id'],
        ]);

        $beforeRoles = $user->roles()->pluck('name')->all();
        $before = $this->userPayload($user, $beforeRoles, false);
        $avatarPath = $user->getRawOriginal('avatar');
        $avatarChanged = false;

        if ($validated['avatar'] ?? null) {
            if ($avatarPath) {
                Storage::disk('public')->delete($avatarPath);
            }

            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $avatarChanged = true;
        }

        if (! empty($validated['password'])) {
            $user->update(['password' => bcrypt($validated['password'])]);
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'avatar' => $avatarPath,
        ]);

        $user->syncRoles($validated['selectedRoles']);
        $user->outlets()->sync($this->outletAssignments($request));

        $afterRoles = $this->auditLogService->roleNames($validated['selectedRoles']);
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

        return $this->ok($user->fresh()->load('roles:id,name'));
    }

    /**
     * DELETE /api/v1/users/{id}
     * Supports comma-separated ids.
     */
    public function destroyUser($id): JsonResponse
    {
        $ids = collect(explode(',', (string) $id))
            ->map(fn ($value) => (int) trim($value))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $this->error('Tidak ada pengguna yang dipilih untuk dihapus.', 422);
        }

        if ($ids->contains(auth()->id())) {
            $this->auditLogService->log(
                event: 'user.delete_blocked',
                module: 'users',
                auditable: null,
                description: 'Penghapusan akun sendiri diblokir.',
                meta: ['severity' => 'warning', 'ids' => $ids->all()],
            );

            return $this->error('Anda tidak dapat menghapus akun Anda sendiri.', 422);
        }

        $users = User::query()->with('roles')->whereIn('id', $ids)->get();

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

                return $this->error('Super-admin terakhir tidak dapat dihapus.', 422);
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

        return $this->noContent();
    }

    /**
     * Build outlet pivot payload from a generic API request.
     */
    protected function outletAssignments(Request $request): array
    {
        $ids = collect($request->input('outlet_ids', []))->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $default = (int) $request->input('default_outlet_id');

        return $ids->mapWithKeys(fn ($id) => [$id => ['is_default' => $id === $default]])->all();
    }
}
