<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

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

        // ضع علامة في الكونتينر للاستدلال لاحقًا
        app()->instance('req.impersonated', $target->getKey());

        // لا نبدّل الـ guard هنا، نكتفي بمعلومة الانتحال لتستخدمها طبقة الخدمة عند الحاجة.
        return $next($request);
    }
}
