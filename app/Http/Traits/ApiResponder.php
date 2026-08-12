<?php

namespace App\Http\Traits;

use App\Http\Responses\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Convenience trait for API controllers.
 */
trait ApiResponder
{
    protected function ok(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $status);
    }

    protected function created(mixed $data = null, string $message = 'Berhasil dibuat'): JsonResponse
    {
        return ApiResponse::created($data, $message);
    }

    protected function noContent(): JsonResponse
    {
        return ApiResponse::noContent();
    }

    protected function error(string $message = 'Terjadi kesalahan', int $status = 400, mixed $errors = null): JsonResponse
    {
        return ApiResponse::error($message, $status, $errors);
    }

    protected function paginated(LengthAwarePaginator $paginator, mixed $resource = null): JsonResponse
    {
        return ApiResponse::paginated($paginator, $resource);
    }

    protected function unauthorized(string $message = 'Unauthenticated'): JsonResponse
    {
        return ApiResponse::unauthorized($message);
    }

    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return ApiResponse::forbidden($message);
    }

    protected function notFound(string $message = 'Data tidak ditemukan'): JsonResponse
    {
        return ApiResponse::notFound($message);
    }

    protected function validationError(mixed $errors, string $message = 'Validasi gagal'): JsonResponse
    {
        return ApiResponse::validationError($errors, $message);
    }
}
