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

class CreateUserService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly UserPayloadService $payloadService
    ) {}

    public function execute(array $data, ?UploadedFile $avatar): User
    {
        $avatarPath = $avatar?->store('avatars', 'public');

        try {
            return DB::transaction(function () use ($data, $avatarPath) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'avatar' => $avatarPath,
                ]);

                $user->assignRole($data['selectedRoles']);

                $this->auditLogService->log(
                    event: 'user.created',
                    module: 'users',
                    auditable: $user,
                    description: 'Pengguna baru dibuat.',
                    after: $this->payloadService->auditPayload(
                        $user,
                        $data['selectedRoles'],
                        $avatarPath !== null
                    ),
                );

                return $user;
            });
        } catch (Throwable $throwable) {
            if ($avatarPath) {
                Storage::disk('public')->delete($avatarPath);
            }

            throw $throwable;
        }
    }
}
