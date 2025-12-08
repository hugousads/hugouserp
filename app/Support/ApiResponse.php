<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arrayable;

class ApiResponse
{
    public static function success(array|Arrayable $data = [], int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => self::toArray($data),
            'meta' => (object) $meta,
        ], $status);
    }

    public static function created(array|Arrayable $data = [], array $meta = []): JsonResponse
    {
        return self::success($data, 201, $meta);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(['success' => true], 204);
    }

    public static function paginated(LengthAwarePaginator $paginator, array $meta = [], ?JsonResource $resourceClass = null): JsonResponse
    {
        $items = $resourceClass ? $resourceClass::collection($paginator)->resolve() : $paginator->items();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => (object) array_merge($meta, [
                'pagination' => [
                    'total' => $paginator->total(),
                    'count' => $paginator->count(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ]),
        ]);
    }

    public static function resource(JsonResource $resource, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $resource->resolve(request()),
        ], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = [], array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => (object) $errors,
            'meta' => (object) $meta,
        ], $status);
    }

    public static function validationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return self::error($message, 422, $errors);
    }

    public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return self::error($message, 403);
    }

    public static function fromException(\Throwable $e, int $status = 500): JsonResponse
    {
        $meta = [];
        $message = config('app.debug') ? $e->getMessage() : __('Something went wrong.');

        if (config('app.debug')) {
            $meta = [
                'exception' => class_basename($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        return self::error($message, $status, [], $meta);
    }

    private static function toArray(array|Arrayable $data): array
    {
        return $data instanceof Arrayable ? $data->toArray() : (array) $data;
    }
}
