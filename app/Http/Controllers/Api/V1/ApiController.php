<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Enhanced API Controller Base
 * 
 * Provides:
 * - Standardized response formatting
 * - Request validation helpers
 * - Rate limiting information
 * - Caching support
 * - Error handling
 */
abstract class ApiController extends Controller
{
    /**
     * API version
     */
    protected string $apiVersion = 'v1';

    /**
     * Default pagination limit
     */
    protected int $defaultPerPage = 25;

    /**
     * Maximum pagination limit
     */
    protected int $maxPerPage = 100;

    /**
     * Cache TTL in seconds (default 5 minutes)
     */
    protected int $cacheTtl = 300;

    /**
     * Get the authenticated store from request
     */
    protected function getStore(Request $request): ?Store
    {
        return $request->get('store');
    }

    /**
     * Get the authenticated user ID
     */
    protected function getAuthUserId(Request $request): ?int
    {
        return $request->user()?->id;
    }

    /**
     * Get the current branch ID
     */
    protected function getBranchId(Request $request): ?int
    {
        return $request->route('branch')?->id ?? $request->user()?->branch_id;
    }

    /**
     * Success response with data
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Success',
        int $code = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    /**
     * Error response
     */
    protected function errorResponse(
        string $message,
        int $code = 400,
        array $errors = [],
        ?string $errorCode = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorCode) {
            $response['error_code'] = $errorCode;
        }

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Paginated response
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $message = 'Success',
        ?string $resourceClass = null
    ): JsonResponse {
        $items = $resourceClass 
            ? $resourceClass::collection($paginator->items())
            : $paginator->items();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Resource response (for single items)
     */
    protected function resourceResponse(
        JsonResource $resource,
        string $message = 'Success',
        int $code = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource,
        ], $code);
    }

    /**
     * Collection response
     */
    protected function collectionResponse(
        ResourceCollection $collection,
        string $message = 'Success'
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $collection,
        ]);
    }

    /**
     * Created response (201)
     */
    protected function createdResponse(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * No content response (204)
     */
    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Not found response
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404, [], 'NOT_FOUND');
    }

    /**
     * Unauthorized response
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401, [], 'UNAUTHORIZED');
    }

    /**
     * Forbidden response
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, 403, [], 'FORBIDDEN');
    }

    /**
     * Validation error response
     */
    protected function validationErrorResponse(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors, 'VALIDATION_ERROR');
    }

    /**
     * Server error response
     */
    protected function serverErrorResponse(string $message = 'Internal server error'): JsonResponse
    {
        return $this->errorResponse($message, 500, [], 'SERVER_ERROR');
    }

    /**
     * Validate request with custom rules
     */
    protected function validateRequest(Request $request, array $rules, array $messages = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Get pagination parameters from request
     */
    protected function getPaginationParams(Request $request): array
    {
        $perPage = (int) $request->input('per_page', $this->defaultPerPage);
        $perPage = min($perPage, $this->maxPerPage);
        
        return [
            'per_page' => $perPage,
            'page' => (int) $request->input('page', 1),
        ];
    }

    /**
     * Get sorting parameters from request
     */
    protected function getSortParams(Request $request, array $allowedFields, string $defaultField = 'created_at'): array
    {
        $sortField = $request->input('sort_by', $defaultField);
        $sortDirection = strtolower($request->input('sort_direction', 'desc'));
        
        // Validate sort field
        if (!in_array($sortField, $allowedFields)) {
            $sortField = $defaultField;
        }
        
        // Validate direction
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        
        return [
            'field' => $sortField,
            'direction' => $sortDirection,
        ];
    }

    /**
     * Cache a response
     */
    protected function cacheResponse(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $ttl = $ttl ?? $this->cacheTtl;
        
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Generate cache key for API response
     */
    protected function generateCacheKey(Request $request, string $prefix = ''): string
    {
        $userId = $this->getAuthUserId($request);
        $branchId = $this->getBranchId($request);
        $queryString = http_build_query($request->query());
        
        $parts = [
            'api',
            $this->apiVersion,
            $prefix ?: $request->path(),
            "user:{$userId}",
            "branch:{$branchId}",
            md5($queryString),
        ];
        
        return implode(':', array_filter($parts));
    }

    /**
     * Clear cache for a prefix
     */
    protected function clearCache(string $prefix): void
    {
        Cache::tags(["api:{$this->apiVersion}", $prefix])->flush();
    }

    /**
     * Transform exception to API response
     */
    protected function handleException(\Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return $this->validationErrorResponse($e->errors());
        }

        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFoundResponse();
        }

        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return $this->unauthorizedResponse();
        }

        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return $this->forbiddenResponse();
        }

        // Log unexpected errors
        logger()->error('API Error', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Don't expose internal errors in production
        $message = config('app.debug') ? $e->getMessage() : 'An unexpected error occurred';
        
        return $this->serverErrorResponse($message);
    }
}
