<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserDisabled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * InvalidateUserSessions Listener
 *
 * SECURITY FIX: Immediately invalidates all sessions and tokens when a user is disabled.
 * This prevents "zombie sessions" where a disabled user can continue to use the system
 * until their existing token expires.
 *
 * NOTE: This listener intentionally does NOT implement ShouldQueue to ensure
 * immediate revocation. Delayed revocation creates a security window where
 * a terminated employee could exfiltrate data or perform destructive actions.
 */
class InvalidateUserSessions
{
    /**
     * Handle the UserDisabled event.
     *
     * Performs immediate (synchronous) session invalidation to prevent zombie sessions.
     */
    public function handle(UserDisabled $event): void
    {
        $user = $event->user;

        try {
            // 1. Revoke all Sanctum tokens (API access)
            if (class_exists(PersonalAccessToken::class)) {
                $tokenCount = $user->tokens()->count();
                $user->tokens()->delete();

                if ($tokenCount > 0) {
                    Log::info('User tokens revoked on disable', [
                        'user_id' => $user->getKey(),
                        'tokens_revoked' => $tokenCount,
                    ]);
                }
            }

            // 2. Invalidate all database sessions (web guard)
            if (config('session.driver') === 'database') {
                $sessionCount = DB::table('sessions')
                    ->where('user_id', $user->getKey())
                    ->count();

                DB::table('sessions')
                    ->where('user_id', $user->getKey())
                    ->delete();

                if ($sessionCount > 0) {
                    Log::info('User sessions invalidated on disable', [
                        'user_id' => $user->getKey(),
                        'sessions_invalidated' => $sessionCount,
                    ]);
                }
            }

            // 3. Invalidate user_sessions tracking records
            if (method_exists($user, 'sessions')) {
                $user->sessions()->delete();
            }

            // 4. Invalidate remember_token to prevent "remember me" login
            if ($user->remember_token) {
                $user->remember_token = null;
                $user->saveQuietly(); // Quiet save to avoid triggering observers
            }

            // 5. Clear any 2FA trusted device cookies by resetting password_changed_at
            // This will force re-authentication on all trusted devices
            if ($user->password_changed_at) {
                $user->password_changed_at = now();
                $user->saveQuietly();
            }

        } catch (\Throwable $e) {
            // Log the error but don't fail - user is already disabled
            Log::error('Failed to fully invalidate user sessions', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
