<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Sanctum\NewAccessToken;

interface AuthServiceInterface
{
    public function guard(?string $name = null);

    public function user(): ?Authenticatable;

    /** @param array<string> $abilities */
    public function issueToken(Authenticatable $user, array $abilities = ['*'], ?string $name = null): NewAccessToken;

    public function attempt(array $credentials, bool $remember = false): bool;

    public function revokeAllTokens(?Authenticatable $user = null): int;

    public function enableImpersonation(?int $asUserId, array $abilities = ['*']): ?NewAccessToken;

    public function findUserByCredential(string $credential): ?User;

    public function attemptMultiFieldLogin(string $credential, string $password, bool $remember = false): array;

    public function initiatePasswordReset(string $email): array;

    public function resetPassword(string $email, string $token, string $password): array;

    public function validateResetToken(string $email, string $token): array;

    /**
     * Change user password with security validations.
     * Invalidates all other sessions and trusted devices.
     *
     * @return array{success: bool, error: ?string}
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): array;
}
