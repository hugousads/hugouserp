<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureBranchAccess
 *
 * - Ensures the authenticated user can access the current branch.
 * - Accepts Super Admin shortcut (role/permission check if using spatie).
 *
 * Usage alias: 'branch.access'
 */
class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        /** @var Branch|null $branch */
        $branch = $request->attributes->get('branch') ?? null;

        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }
        if (! $branch instanceof Branch) {
            // Some endpoints (e.g., file uploads) are authenticated but not branch-scoped.
            return $next($request);
        }

        // Super admin shortcut
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'super-admin'])) {
            return $next($request);
        }

        // Permission-based bypass (e.g., wildcard permission or specific access-all permission)
        if (method_exists($user, 'hasPermissionTo')) {
            if ($user->hasPermissionTo('*') || $user->hasPermissionTo('access-all-branches')) {
                return $next($request);
            }
        }

        // Generic relationship checks (adjust to your schema)
        $can = false;

        // 1) via policy (if defined): $user->can('view', $branch)
        if (method_exists($user, 'can') && $user->can('view', $branch)) {
            $can = true;
        }

        // 2) fallback: check user->branches relation or pivot
        if (! $can && method_exists($user, 'branches')) {
            $can = $user->branches()->whereKey($branch->getKey())->exists();
        }

        if (! $can) {
            return $this->error('You are not allowed to access this branch.', 403);
        }

        return $next($request);
    }

    protected function error(string $message, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
