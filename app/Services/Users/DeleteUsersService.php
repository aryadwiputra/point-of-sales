<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteUsersService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly UserPayloadService $payloadService
    ) {}

    public function execute(int|string $ids): void
    {
        $userIds = collect(explode(',', (string) $ids))
            ->map(fn (string $id) => trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $avatarPaths = DB::transaction(function () use ($userIds) {
            $users = User::query()
                ->with('roles')
                ->whereIn('id', $userIds)
                ->get();

            foreach ($users as $user) {
                $this->auditLogService->log(
                    event: 'user.deleted',
                    module: 'users',
                    auditable: $user,
                    description: 'Pengguna dihapus.',
                    before: $this->payloadService->auditPayload(
                        $user,
                        $user->roles->pluck('name')->all(),
                        false
                    ),
                );
            }

            User::query()->whereIn('id', $userIds)->delete();

            return $users
                ->map(fn (User $user) => $user->getRawOriginal('avatar'))
                ->filter()
                ->all();
        });

        foreach ($avatarPaths as $avatarPath) {
            Storage::disk('public')->delete($avatarPath);
        }
    }
}
