<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

trait ChecksPermissions
{
    protected function has($user, string $permission): bool
    {
        if (! $user) {
            return false;
        }
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'super-admin'])) {
            return true;
        }
        if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($permission)) {
            return true;
        }
        if (method_exists($user, 'can') && $user->can($permission)) {
            return true;
        }

        return false;
    }
}
