<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class UserPayloadService
{
    public function createPayload(): array
    {
        return [
            'roles' => $this->roleOptions(),
        ];
    }

    public function editPayload(User $user): array
    {
        return [
            'roles' => $this->roleOptions(),
            'user' => $user->load([
                'roles' => fn ($query) => $query->select('id', 'name'),
                'roles.permissions' => fn ($query) => $query->select('id', 'name'),
            ]),
        ];
    }

    public function auditPayload(User $user, array $roles, bool $avatarChanged): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'avatar_changed' => $avatarChanged,
            'roles' => $this->normalizeNames($roles),
        ];
    }

    public function normalizeNames(iterable $items): array
    {
        return collect($items)
            ->map(fn ($item) => is_string($item) ? $item : $item->name)
            ->filter()
            ->sort()
            ->values()
            ->all();
    }

    private function roleOptions(): Collection
    {
        return Role::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }
}
