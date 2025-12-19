<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attachment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Document;
use App\Models\HREmployee;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\RentalContract;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

class AttachmentAuthorizationService
{
    /**
     * Map attachable models to their required base permission.
     *
     * @var array<class-string,string>
     */
    private const ATTACHABLE_PERMISSIONS = [
        Branch::class => 'branches.view',
        Customer::class => 'customers.view',
        Supplier::class => 'suppliers.view',
        Product::class => 'inventory.products.view',
        Sale::class => 'sales.view',
        Purchase::class => 'purchases.view',
        RentalContract::class => 'rental.contracts.view',
        HREmployee::class => 'hrm.employees.view',
        Document::class => 'documents.view',
    ];

    /**
     * @param class-string $modelType
     *
     * @throws AuthorizationException
     */
    public function authorizeForModel(Authenticatable|User $user, string $modelType, int $modelId): object
    {
        $modelClass = ltrim($modelType, '\\');

        if (! array_key_exists($modelClass, self::ATTACHABLE_PERMISSIONS)) {
            throw new AuthorizationException('Unsupported resource.');
        }

        /** @var object $attachable */
        $attachable = $modelClass::findOrFail($modelId);

        $this->authorizeModel($user, $attachable);

        return $attachable;
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeForAttachment(Authenticatable|User $user, Attachment $attachment): object
    {
        $attachable = $attachment->attachable;

        if (! $attachable) {
            throw new AuthorizationException('Attachment is orphaned.');
        }

        $this->authorizeModel($user, $attachable);

        return $attachable;
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeModel(Authenticatable|User $user, object $attachable): void
    {
        $modelClass = get_class($attachable);

        if (! array_key_exists($modelClass, self::ATTACHABLE_PERMISSIONS)) {
            throw new AuthorizationException('Unsupported resource.');
        }

        $permission = self::ATTACHABLE_PERMISSIONS[$modelClass];

        if (! $user->can($permission)) {
            throw new AuthorizationException('Forbidden');
        }

        if (isset($attachable->branch_id) && $user->branch_id && $attachable->branch_id !== $user->branch_id) {
            throw new AuthorizationException('Forbidden');
        }

        if (Gate::getPolicyFor($attachable)) {
            Gate::forUser($user)->authorize('view', $attachable);
        }
    }
}
