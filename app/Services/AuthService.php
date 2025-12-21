<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\Contracts\AuthServiceInterface;
use App\Traits\HandlesServiceErrors;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * Authentication facade for API (Sanctum-based) and web login.
 * Centralizes token creation, guard switching, impersonation, and password reset.
 */
class AuthService implements AuthServiceInterface
{
    use HandlesServiceErrors;

    public function guard(?string $name = null)
    {
        return Auth::guard($name ?? 'api');
    }

    public function user(): ?Authenticatable
    {
        return $this->guard()->user() ?? Auth::user();
    }

    /**
     * Issue a Sanctum token with abilities.
     *
     * @param  array<string>  $abilities
     */
    public function issueToken(Authenticatable $user, array $abilities = ['*'], ?string $name = null): NewAccessToken
    {
        return $this->handleServiceOperation(
            callback: function () use ($user, $abilities, $name) {
                $tokenName = $name ?: ('api-'.Str::random(6));

                return $user->createToken($tokenName, $abilities);
            },
            operation: 'issueToken',
            context: ['user_id' => $user->getAuthIdentifier(), 'abilities' => $abilities]
        );
    }

    /**
     * Basic email/password login (if you keep web guard available).
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        return $this->handleServiceOperation(
            callback: fn () => Auth::guard('web')->attempt($credentials, $remember),
            operation: 'attempt',
            context: ['remember' => $remember],
            defaultValue: false
        );
    }

    /**
     * Revoke all user tokens (logout all devices).
     */
    public function revokeAllTokens(?Authenticatable $user = null): int
    {
        return $this->handleServiceOperation(
            callback: function () use ($user) {
                $user = $user ?: $this->user();
                if (! $user || ! method_exists($user, 'tokens')) {
                    return 0;
                }

                return (int) $user->tokens()->delete();
            },
            operation: 'revokeAllTokens',
            context: ['user_id' => $user?->getAuthIdentifier()],
            defaultValue: 0
        );
    }

    /**
     * Mark current request as impersonated (safe, no guard switch).
     */
    public function enableImpersonation(?int $asUserId, array $abilities = ['*']): ?NewAccessToken
    {
        return $this->handleServiceOperation(
            callback: function () use ($asUserId, $abilities) {
                if (! $asUserId) {
                    return null;
                }
                $actor = $this->user();
                if (! $actor) {
                    return null;
                }

                if (method_exists($actor, 'hasAnyRole') && $actor->hasAnyRole(['Super Admin', 'super-admin'])) {
                } elseif (method_exists($actor, 'hasPermissionTo') && $actor->hasPermissionTo('impersonate.users')) {
                } else {
                    abort(403, 'Not allowed to impersonate');
                }

                $as = (\App\Models\User::query()->findOrFail($asUserId));
                $token = $as->createToken('impersonate-'.Str::random(4), $abilities, now()->addHours(4));
                request()->attributes->set('impersonating', true);
                request()->attributes->set('impersonated_by', $actor->getKey());

                return $token;
            },
            operation: 'enableImpersonation',
            context: ['as_user_id' => $asUserId],
            defaultValue: null
        );
    }

    /**
     * Find user by email, phone, or username.
     */
    public function findUserByCredential(string $credential): ?User
    {
        return $this->handleServiceOperation(
            callback: function () use ($credential) {
                $credential = trim($credential);

                return User::query()
                    ->where('email', $credential)
                    ->orWhere('phone', $credential)
                    ->orWhere('username', $credential)
                    ->first();
            },
            operation: 'findUserByCredential',
            context: ['credential' => $credential],
            defaultValue: null
        );
    }

    /**
     * Attempt multi-field login with proper error messages.
     * Returns: ['success' => bool, 'error' => ?string, 'user' => ?User]
     */
    public function attemptMultiFieldLogin(string $credential, string $password, bool $remember = false): array
    {
        return $this->handleServiceOperation(
            callback: function () use ($credential, $password, $remember) {
                $user = $this->findUserByCredential($credential);

                if (! $user) {
                    return [
                        'success' => false,
                        'error' => 'user_not_found',
                        'user' => null,
                    ];
                }

                if (! $user->is_active) {
                    return [
                        'success' => false,
                        'error' => 'account_inactive',
                        'user' => null,
                    ];
                }

                if (! Hash::check($password, $user->password)) {
                    return [
                        'success' => false,
                        'error' => 'invalid_password',
                        'user' => null,
                    ];
                }

                Auth::login($user, $remember);

                return [
                    'success' => true,
                    'error' => null,
                    'user' => $user,
                ];
            },
            operation: 'attemptMultiFieldLogin',
            context: ['credential' => $credential, 'remember' => $remember],
            defaultValue: ['success' => false, 'error' => 'login_failed', 'user' => null]
        );
    }

    /**
     * Initiate password reset and send email.
     * Returns: ['success' => bool, 'error' => ?string]
     */
    public function initiatePasswordReset(string $email): array
    {
        return $this->handleServiceOperation(
            callback: function () use ($email) {
                $user = User::where('email', $email)->first();

                if (! $user) {
                    return [
                        'success' => false,
                        'error' => 'user_not_found',
                    ];
                }

                if (! $user->is_active) {
                    return [
                        'success' => false,
                        'error' => 'account_inactive',
                    ];
                }

                DB::table('password_reset_tokens')
                    ->where('email', $email)
                    ->delete();

                $token = Str::random(64);
                $hashedToken = Hash::make($token);

                DB::table('password_reset_tokens')->insert([
                    'email' => $email,
                    'token' => $hashedToken,
                    'created_at' => now(),
                ]);

                $resetUrl = route('password.reset', ['token' => $token, 'email' => $email]);

                Mail::send('emails.password-reset', [
                    'user' => $user,
                    'resetUrl' => $resetUrl,
                    'expiresIn' => 60,
                ], function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject(__('Reset Your Password').' - '.config('app.name'));
                });

                $this->logServiceInfo('initiatePasswordReset', 'Password reset email sent', ['email' => $email]);

                return [
                    'success' => true,
                    'error' => null,
                ];
            },
            operation: 'initiatePasswordReset',
            context: ['email' => $email],
            defaultValue: ['success' => false, 'error' => 'email_failed']
        );
    }

    /**
     * Validate password reset token.
     * Returns: ['valid' => bool, 'error' => ?string]
     */
    public function validateResetToken(string $email, string $token): array
    {
        return $this->handleServiceOperation(
            callback: function () use ($email, $token) {
                $record = DB::table('password_reset_tokens')
                    ->where('email', $email)
                    ->first();

                if (! $record) {
                    return [
                        'valid' => false,
                        'error' => 'invalid_token',
                    ];
                }

                if (! Hash::check($token, $record->token)) {
                    return [
                        'valid' => false,
                        'error' => 'invalid_token',
                    ];
                }

                $createdAt = Carbon::parse($record->created_at);
                if ($createdAt->addMinutes(60)->isPast()) {
                    return [
                        'valid' => false,
                        'error' => 'expired_token',
                    ];
                }

                return [
                    'valid' => true,
                    'error' => null,
                ];
            },
            operation: 'validateResetToken',
            context: ['email' => $email],
            defaultValue: ['valid' => false, 'error' => 'validation_failed']
        );
    }

    /**
     * Reset user password.
     * Returns: ['success' => bool, 'error' => ?string]
     */
    public function resetPassword(string $email, string $token, string $password): array
    {
        return $this->handleServiceOperation(
            callback: function () use ($email, $token, $password) {
                $validation = $this->validateResetToken($email, $token);

                if (! $validation['valid']) {
                    return [
                        'success' => false,
                        'error' => $validation['error'],
                    ];
                }

                $user = User::where('email', $email)->first();

                if (! $user) {
                    return [
                        'success' => false,
                        'error' => 'user_not_found',
                    ];
                }

                $user->password = Hash::make($password);
                $user->save();

                DB::table('password_reset_tokens')
                    ->where('email', $email)
                    ->delete();

                $this->logServiceInfo('resetPassword', 'Password reset successful', ['email' => $email]);

                return [
                    'success' => true,
                    'error' => null,
                ];
            },
            operation: 'resetPassword',
            context: ['email' => $email],
            defaultValue: ['success' => false, 'error' => 'reset_failed']
        );
    }
}
