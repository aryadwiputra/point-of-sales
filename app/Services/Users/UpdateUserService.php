<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UpdateUserService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly UserPayloadService $payloadService
    ) {}

    public function execute(User $user, array $data, ?UploadedFile $avatar): User
    {
        $previousAvatarPath = $user->getRawOriginal('avatar');
        $avatarPath = $avatar?->store('avatars', 'public') ?? $previousAvatarPath;
        $avatarChanged = $avatar !== null;

        try {
            $user = DB::transaction(function () use ($user, $data, $avatarPath, $avatarChanged) {
                $beforeRoles = $this->payloadService->normalizeNames($user->roles()->pluck('name'));
                $before = $this->payloadService->auditPayload($user, $beforeRoles, false);
                $payload = [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'avatar' => $avatarPath,
                ];

                if (filled($data['password'] ?? null)) {
                    $payload['password'] = Hash::make($data['password']);
                }

                $user->update($payload);
                $user->syncRoles($data['selectedRoles']);

                $afterRoles = $this->payloadService->normalizeNames($data['selectedRoles']);

                $this->auditLogService->log(
                    event: 'user.updated',
                    module: 'users',
                    auditable: $user,
                    description: 'Data pengguna diperbarui.',
                    before: $before,
                    after: $this->payloadService->auditPayload($user->fresh(), $afterRoles, $avatarChanged),
                );

                if ($beforeRoles !== $afterRoles) {
                    $this->auditLogService->log(
                        event: 'user.role_changed',
                        module: 'users',
                        auditable: $user,
                        description: 'Role pengguna diperbarui.',
                        before: ['roles' => $beforeRoles],
                        after: ['roles' => $afterRoles],
                    );
                }

                return $user->fresh();
            });
        } catch (Throwable $throwable) {
            if ($avatarChanged && $avatarPath) {
                Storage::disk('public')->delete($avatarPath);
            }

            throw $throwable;
        }

        if ($avatarChanged && $previousAvatarPath) {
            Storage::disk('public')->delete($previousAvatarPath);
        }

        return $user;
    }
}
