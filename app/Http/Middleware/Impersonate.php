<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impersonate Middleware
 *
 * Allows authorized users (Super Admins or users with impersonate.users permission)
 * to act as another user for support/debugging purposes.
 *
 * SECURITY: All actions performed during impersonation are tracked with both
 * the actual performer (impersonator) and the impersonated user for audit purposes.
 */
class Impersonate
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        $targetRef = $request->headers->get('X-Impersonate-User');

        if (! $actor || ! $targetRef) {
            return $next($request);
        }

        $can = (method_exists($actor, 'hasPermissionTo') && $actor->hasPermissionTo('impersonate.users'))
            || (method_exists($actor, 'hasAnyRole') && $actor->hasAnyRole(['Super Admin', 'super-admin']));

        if (! $can) {
            return response()->json(['success' => false, 'message' => 'Impersonation not allowed.'], 403);
        }

        /** @var User|null $target */
        $target = Str::contains($targetRef, '@')
            ? User::where('email', $targetRef)->first()
            : User::query()->find($targetRef);

        if (! $target) {
            return response()->json(['success' => false, 'message' => 'Impersonation target not found.'], 404);
        }

        // SECURITY FIX: Store both the actual performer and impersonated user in the container
        // This ensures audit logs can track who REALLY performed an action
        app()->instance('req.impersonated', $target->getKey());
        app()->instance('req.impersonated_by', $actor->getKey());  // The ACTUAL user (impersonator)

        // Also set on request attributes for easy access
        $request->attributes->set('impersonating', true);
        $request->attributes->set('impersonated_user_id', $target->getKey());
        $request->attributes->set('impersonated_by_user_id', $actor->getKey());

        return $next($request);
    }

    /**
     * Get the ID of the actual user performing actions (the impersonator).
     * Returns null if not in an impersonation session.
     */
    public static function getActualPerformerId(): ?int
    {
        if (app()->has('req.impersonated_by')) {
            return (int) app('req.impersonated_by');
        }

        $req = request();
        if ($req && $req->attributes->has('impersonated_by_user_id')) {
            return (int) $req->attributes->get('impersonated_by_user_id');
        }

        return null;
    }

    /**
     * Get the ID of the user being impersonated.
     * Returns null if not in an impersonation session.
     */
    public static function getImpersonatedUserId(): ?int
    {
        if (app()->has('req.impersonated')) {
            return (int) app('req.impersonated');
        }

        $req = request();
        if ($req && $req->attributes->has('impersonated_user_id')) {
            return (int) $req->attributes->get('impersonated_user_id');
        }

        return null;
    }

    /**
     * Check if the current request is being performed during impersonation.
     */
    public static function isImpersonating(): bool
    {
        return self::getImpersonatedUserId() !== null;
    }
}
