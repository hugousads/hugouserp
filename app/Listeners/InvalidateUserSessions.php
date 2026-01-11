<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserDisabled;
use App\Traits\InvalidatesUserSessions as InvalidatesUserSessionsTrait;

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
    use InvalidatesUserSessionsTrait;

    /**
     * Handle the UserDisabled event.
     *
     * Performs immediate (synchronous) session invalidation to prevent zombie sessions.
     */
    public function handle(UserDisabled $event): void
    {
        $this->performFullSecurityInvalidation($event->user);
    }
}
