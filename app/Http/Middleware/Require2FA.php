<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require2FA Middleware
 *
 * Enforces two-factor authentication requirements based on system settings.
 *
 * SECURITY FIX: Validates "trusted device" sessions against password_changed_at
 * to invalidate 2FA bypass tokens when password is changed.
 */
class Require2FA
{
    public function __construct(protected SettingsService $settingsService) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        $is2FAEnabled = $this->settingsService->get('security.2fa_enabled', false);
        $is2FARequired = $this->settingsService->get('security.2fa_required', false);

        if (! $is2FAEnabled) {
            return $next($request);
        }

        if ($is2FARequired && ! $user->hasTwoFactorEnabled()) {
            if (! $request->routeIs('2fa.setup', '2fa.setup.*', 'logout')) {
                return redirect()->route('2fa.setup')
                    ->with('warning', __('Two-factor authentication is required. Please set it up to continue.'));
            }
        }

        if ($user->hasTwoFactorEnabled() && ! $this->is2FAVerified($request, $user)) {
            if (! $request->routeIs('2fa.challenge', '2fa.challenge.*', 'logout')) {
                return redirect()->route('2fa.challenge');
            }
        }

        return $next($request);
    }

    /**
     * Check if 2FA has been verified for this session.
     *
     * SECURITY FIX: Validates that the 2FA verification occurred AFTER
     * the last password change to prevent "remember 2FA" bypass attacks
     * on stolen/compromised devices.
     */
    protected function is2FAVerified(Request $request, $user): bool
    {
        // Check if 2FA has been verified in this session
        if (! session('2fa_verified')) {
            return false;
        }

        // SECURITY FIX: If password was changed after 2FA verification,
        // require re-verification to prevent bypass attacks
        $verifiedAt = $this->parseTimestamp(session('2fa_verified_at'));
        $passwordChangedAt = $user->password_changed_at;

        if ($verifiedAt && $passwordChangedAt) {
            // If password was changed after 2FA verification, invalidate the session
            if ($passwordChangedAt->gt($verifiedAt)) {
                // Clear 2FA session data
                session()->forget(['2fa_verified', '2fa_verified_at', '2fa_trusted_device']);

                return false;
            }
        }

        // SECURITY FIX: Check "trusted device" cookie validity
        // If using trusted device feature, validate against password_changed_at
        if ($this->isTrustedDeviceSession($request)) {
            $trustedAt = $this->parseTimestamp(session('2fa_trusted_device_at'));

            if ($trustedAt && $passwordChangedAt && $passwordChangedAt->gt($trustedAt)) {
                // Password was changed after device was trusted, invalidate trust
                session()->forget(['2fa_trusted_device', '2fa_trusted_device_at']);

                return false;
            }

            return true;
        }

        return true;
    }

    /**
     * Check if this is a trusted device session.
     */
    protected function isTrustedDeviceSession(Request $request): bool
    {
        // Check session-based trusted device flag
        if (session('2fa_trusted_device')) {
            return true;
        }

        // Check cookie-based trusted device (if implemented)
        if ($request->cookie('2fa_trusted_device')) {
            // Validate the cookie signature here if needed
            return true;
        }

        return false;
    }

    /**
     * Safely parse a timestamp value to Carbon instance.
     *
     * @param  mixed  $value  The timestamp value to parse
     * @return Carbon|null
     */
    protected function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon || $value instanceof \Carbon\Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) || is_numeric($value)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
