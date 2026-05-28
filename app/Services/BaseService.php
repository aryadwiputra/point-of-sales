<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

abstract class BaseService
{
    public function execute(mixed ...$payload): mixed
    {
        try {
            return $this->success($this->handle(...$payload));
        } catch (ValidationException|AuthorizationException|ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            return $this->error($throwable);
        }
    }

    abstract protected function handle(mixed ...$payload): mixed;

    protected function success(mixed $data = null, string $message = 'Success'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    protected function error(Throwable $throwable): array
    {
        Log::error($throwable);

        return [
            'success' => false,
            'message' => config('app.debug')
                ? $throwable->getMessage()
                : 'Internal Server Error',
            'data' => null,
        ];
    }
}
