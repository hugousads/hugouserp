<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetUserBranchContext
 *
 * Sets the branch context based on the authenticated user's branch_id.
 * This ensures that models using HasBranch trait automatically scope
 * queries to the user's branch, preventing cross-branch data leakage.
 *
 * Usage: Add to web middleware group or apply to specific routes
 */
class SetUserBranchContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if ($user && isset($user->branch_id) && $user->branch_id) {
            // Set branch context in request attributes
            $request->attributes->set('branch_id', (int) $user->branch_id);
            
            // Also store in service container for easy access in services
            app()->instance('req.branch_id', (int) $user->branch_id);
            
            // Store user's branch in container if branch model exists
            if ($user->relationLoaded('branch') || method_exists($user, 'branch')) {
                $branch = $user->branch ?? $user->branch()->first();
                if ($branch) {
                    $request->attributes->set('branch', $branch);
                }
            }
        }

        return $next($request);
    }
}
