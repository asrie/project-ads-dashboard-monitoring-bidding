<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Standard API response envelope used across every endpoint.
 */
final class ApiResponse
{
    public static function success(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => self::meta($meta),
            'errors' => [],
        ], $status);
    }

    /**
     * @param  array<int, array{code: string, message: string, field: string|null}>  $errors
     */
    public static function error(array $errors, int $status = 400, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'meta' => self::meta($meta),
            'errors' => $errors,
        ], $status);
    }

    /**
     * Build a success response from a paginator, moving pagination into meta.
     *
     * @param  array<int, mixed>  $items  already-transformed items
     */
    public static function paginated(LengthAwarePaginator $paginator, array $items): JsonResponse
    {
        return self::success($items, [
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    private static function meta(array $extra): array
    {
        return array_merge([
            'request_id' => (string) Str::uuid(),
            'timestamp' => now()->toIso8601String(),
        ], $extra);
    }
}
