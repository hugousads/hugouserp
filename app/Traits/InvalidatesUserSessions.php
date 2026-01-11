<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * InvalidatesUserSessions Trait
 *
 * Provides reusable session invalidation logic for security operations.
 * Used when a user is disabled, password is changed, or other security events occur.
 */
trait InvalidatesUserSessions
{
    /**
     * Invalidate all sessions and tokens for a user.
     *
     * @param  User  $user  The user whose sessions to invalidate
     * @param  string|null  $exceptSessionId  Session ID to preserve (for password change by logged-in user)
     * @param  int|null  $exceptTokenId  Token ID to preserve (for API password change)
     * @return array{tokens_revoked: int, sessions_invalidated: int}
     */
    protected function invalidateUserSessions(
        User $user,
        ?string $exceptSessionId = null,
        ?int $exceptTokenId = null
    ): array {
        $result = [
            'tokens_revoked' => 0,
            'sessions_invalidated' => 0,
        ];

        try {
            // 1. Revoke Sanctum tokens
            if (class_exists(PersonalAccessToken::class) && method_exists($user, 'tokens')) {
                $query = $user->tokens();
                if ($exceptTokenId) {
                    $query->where('id', '!=', $exceptTokenId);
                }
                $result['tokens_revoked'] = $query->count();
                $query->delete();
            }

            // 2. Invalidate database sessions
            if (config('session.driver') === 'database') {
                $query = DB::table('sessions')->where('user_id', $user->getKey());
                if ($exceptSessionId) {
                    $query->where('id', '!=', $exceptSessionId);
                }
                $result['sessions_invalidated'] = $query->count();
                $query->delete();
            }

            // 3. Clean up user_sessions tracking records
            if (method_exists($user, 'sessions')) {
                $sessionsQuery = $user->sessions();
                if ($exceptSessionId) {
                    $sessionsQuery->where('session_id', '!=', $exceptSessionId);
                }
                $sessionsQuery->delete();
            }

            if ($result['tokens_revoked'] > 0 || $result['sessions_invalidated'] > 0) {
                Log::info('User sessions invalidated', [
                    'user_id' => $user->getKey(),
                    'tokens_revoked' => $result['tokens_revoked'],
                    'sessions_invalidated' => $result['sessions_invalidated'],
                    'preserved_session' => $exceptSessionId !== null,
                    'preserved_token' => $exceptTokenId !== null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to invalidate user sessions', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Invalidate remember token for a user.
     * This prevents "remember me" auto-login.
     */
    protected function invalidateRememberToken(User $user): void
    {
        try {
            if ($user->remember_token) {
                $user->remember_token = null;
                $user->saveQuietly();
            }
        } catch (\Throwable $e) {
            Log::error('Failed to invalidate remember token', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Invalidate 2FA trusted device tokens by updating password_changed_at.
     * The Require2FA middleware validates trusted devices against this timestamp.
     */
    protected function invalidateTrustedDevices(User $user): void
    {
        try {
            $user->password_changed_at = now();
            $user->saveQuietly();
        } catch (\Throwable $e) {
            Log::error('Failed to invalidate trusted devices', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Full security invalidation - revokes all access methods for a user.
     * Use when disabling a user account or responding to a security incident.
     */
    protected function performFullSecurityInvalidation(User $user): array
    {
        $result = $this->invalidateUserSessions($user);
        $this->invalidateRememberToken($user);
        $this->invalidateTrustedDevices($user);

        return $result;
    }
}
