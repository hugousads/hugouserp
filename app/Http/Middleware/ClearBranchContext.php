<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\BranchContextManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ClearBranchContext - Clears branch context cache after request
 *
 * This middleware ensures that the BranchContextManager cache is cleared
 * after each request to prevent stale data in subsequent requests.
 */
class ClearBranchContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Clear cache after request is completed
        BranchContextManager::clearCache();
    }
}
