<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\UserPreference;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLogout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            // Use cached preferences to avoid DB query on every request
            $preferences = UserPreference::cachedForUser($user->id);

            if ($preferences->auto_logout) {
                $lastActivity = session('last_activity');
                $timeout = $preferences->session_timeout * 60;

                if ($lastActivity && (time() - $lastActivity) > $timeout) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')
                        ->with('warning', __('Your session has expired due to inactivity.'));
                }
            }

            session(['last_activity' => time()]);
        }

        return $next($request);
    }
}
