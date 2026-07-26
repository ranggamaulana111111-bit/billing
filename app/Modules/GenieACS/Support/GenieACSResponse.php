<?php

namespace App\Modules\GenieACS\Support;

/**
 * Standardized response builders for GenieACS operations.
 *
 * Provides static helpers for consistent API response formatting
 * across controllers and service methods.
 */
class GenieACSResponse
{
    /**
     * Build a success response envelope.
     *
     * @param  mixed  $data  Response payload
     * @return array{success: bool, message: string, data: mixed}
     */
    public static function success(mixed $data = null, string $message = 'Success'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Build a failure response envelope.
     *
     * @param  mixed  $errors  Error details
     * @return array{success: bool, message: string, errors: mixed}
     */
    public static function failed(string $message = 'Failed', mixed $errors = null): array
    {
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];
    }

    /**
     * Build a paginated response envelope.
     *
     * @param  array<int, mixed>  $items
     * @return array{success: bool, data: array, pagination: array{total: int, per_page: int, current_page: int, last_page: int}}
     */
    public static function pagination(array $items, int $total, int $page, int $perPage): array
    {
        return [
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / max($perPage, 1)),
            ],
        ];
    }

    /**
     * Build a metadata response envelope.
     *
     * @param  array<string, mixed>  $meta
     * @return array{success: bool, meta: array}
     */
    public static function metadata(array $meta): array
    {
        return [
            'success' => true,
            'meta' => $meta,
        ];
    }
}
