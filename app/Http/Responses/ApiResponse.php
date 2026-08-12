<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    /**
     * Success response (200/201).
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Error response.
     */
    public static function error(string $message = 'Terjadi kesalahan', int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Paginated resource collection (Laravel paginator → JSON).
     */
    public static function paginated(LengthAwarePaginator $paginator, mixed $resource = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $resource ?? $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * 201 Created.
     */
    public static function created(mixed $data = null, string $message = 'Berhasil dibuat'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * 204 No Content.
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * 401 Unauthorized.
     */
    public static function unauthorized(string $message = 'Unauthenticated'): JsonResponse
    {
        return self::error($message, 401);
    }

    /**
     * 403 Forbidden.
     */
    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return self::error($message, 403);
    }

    /**
     * 404 Not Found.
     */
    public static function notFound(string $message = 'Data tidak ditemukan'): JsonResponse
    {
        return self::error($message, 404);
    }

    /**
     * 422 Unprocessable Entity (validation).
     */
    public static function validationError(mixed $errors, string $message = 'Validasi gagal'): JsonResponse
    {
        return self::error($message, 422, $errors);
    }
}
