<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\StoreToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateStoreToken
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $token = $this->getTokenFromRequest($request);

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'API token required.',
            ], 401);
        }

        $storeToken = StoreToken::where('token', $token)->first();

        if (! $storeToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API token.',
            ], 401);
        }

        if ($storeToken->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'API token has expired.',
            ], 401);
        }

        $store = $storeToken->store;

        if (! $store || ! $store->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Store is not active.',
            ], 403);
        }

        if (empty($abilities) && ! $storeToken->hasAbility('*')) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint requires explicit token abilities.',
            ], 403);
        }

        foreach ($abilities as $ability) {
            if (! $storeToken->hasAbility($ability)) {
                return response()->json([
                    'success' => false,
                    'message' => "Token does not have the '{$ability}' ability.",
                ], 403);
            }
        }

        $storeToken->touchLastUsed();

        $request->merge([
            'store' => $store,
            'store_token' => $storeToken,
        ]);

        return $next($request);
    }

    protected function getTokenFromRequest(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return $request->query('api_token') ?? $request->input('api_token');
    }
}
