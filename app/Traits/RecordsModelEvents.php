<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * RecordsModelEvents Trait
 * 
 * Enhanced Event Sourcing for better audit trail.
 * Records all model changes with full context for audit/replay.
 */
trait RecordsModelEvents
{
    /**
     * Boot the trait
     */
    public static function bootRecordsModelEvents(): void
    {
        static::created(function (Model $model) {
            $model->recordEvent('created', [], $model->getAuditableAttributes());
        });

        static::updated(function (Model $model) {
            $original = $model->getOriginal();
            $changes = $model->getChanges();
            
            // Filter out timestamps and hidden attributes
            $hiddenFields = $model->getHidden();
            $excludeFields = array_merge($hiddenFields, ['updated_at', 'remember_token']);
            
            $oldValues = array_diff_key($original, array_flip($excludeFields));
            $newValues = array_diff_key($changes, array_flip($excludeFields));
            
            // Only record if there are meaningful changes
            if (!empty($newValues)) {
                $oldFiltered = array_intersect_key($oldValues, $newValues);
                $model->recordEvent('updated', $oldFiltered, $newValues);
            }
        });

        static::deleted(function (Model $model) {
            $model->recordEvent('deleted', $model->getAuditableAttributes(), []);
        });

        // Support for soft deletes
        if (method_exists(static::class, 'bootSoftDeletes')) {
            static::restored(function (Model $model) {
                $model->recordEvent('restored', [], $model->getAuditableAttributes());
            });

            static::forceDeleted(function (Model $model) {
                $model->recordEvent('force_deleted', $model->getAuditableAttributes(), []);
            });
        }
    }

    /**
     * Record an event to the audit log
     */
    public function recordEvent(string $action, array $oldValues, array $newValues, ?string $description = null): void
    {
        try {
            $request = request();
            $user = auth()->user();
            
            AuditLog::create([
                'user_id' => $user?->getKey(),
                'branch_id' => $this->getBranchIdForAudit(),
                'module_key' => $this->getModuleKeyForAudit(),
                'action' => $this->getActionDescription($action),
                'subject_type' => static::class,
                'subject_id' => $this->getKey(),
                'auditable_type' => static::class,
                'auditable_id' => $this->getKey(),
                'ip' => $request?->ip(),
                'user_agent' => (string) $request?->userAgent(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'meta' => $this->getAuditMeta($action, $description),
            ]);
        } catch (\Throwable $e) {
            // Log the error but don't break the main operation
            logger()->error('Failed to record audit event', [
                'model' => static::class,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get attributes suitable for auditing
     */
    protected function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();
        $hidden = $this->getHidden();
        $exclude = array_merge($hidden, ['updated_at', 'created_at', 'remember_token', 'password']);
        
        return array_diff_key($attributes, array_flip($exclude));
    }

    /**
     * Get branch ID for audit (override in model if needed)
     */
    protected function getBranchIdForAudit(): ?int
    {
        if (property_exists($this, 'branch_id') && $this->branch_id) {
            return (int) $this->branch_id;
        }
        
        if (method_exists($this, 'branch')) {
            return $this->branch_id ?? null;
        }
        
        return auth()->user()?->branch_id;
    }

    /**
     * Get module key for audit (override in model if needed)
     */
    protected function getModuleKeyForAudit(): ?string
    {
        // Try to derive module from model name
        $className = class_basename(static::class);
        
        $moduleMap = [
            'Sale' => 'sales',
            'SaleItem' => 'sales',
            'Purchase' => 'purchases',
            'PurchaseItem' => 'purchases',
            'Product' => 'inventory',
            'StockMovement' => 'inventory',
            'Customer' => 'customers',
            'Supplier' => 'suppliers',
            'User' => 'users',
            'Employee' => 'hr',
            'Project' => 'projects',
        ];
        
        return $moduleMap[$className] ?? strtolower($className);
    }

    /**
     * Get action description
     */
    protected function getActionDescription(string $action): string
    {
        $modelName = class_basename(static::class);
        return "{$modelName}:{$action}";
    }

    /**
     * Get additional metadata for audit
     */
    protected function getAuditMeta(string $action, ?string $description): array
    {
        $meta = [
            'timestamp' => now()->toIso8601String(),
            'model' => static::class,
        ];
        
        if ($description) {
            $meta['description'] = $description;
        }
        
        // Add model display name if available
        if (method_exists($this, 'getDisplayName')) {
            $meta['display_name'] = $this->getDisplayName();
        }
        
        return $meta;
    }

    /**
     * Record a custom event manually
     */
    public function recordCustomEvent(string $action, array $data = [], ?string $description = null): void
    {
        $this->recordEvent($action, [], $data, $description);
    }

    /**
     * Get audit history for this model
     */
    public function getAuditHistory(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where(function ($query) {
            $query->where('subject_type', static::class)
                  ->where('subject_id', $this->getKey());
        })->orWhere(function ($query) {
            $query->where('auditable_type', static::class)
                  ->where('auditable_id', $this->getKey());
        })
        ->orderByDesc('created_at')
        ->limit($limit)
        ->get();
    }

    /**
     * Replay events to reconstruct state at a point in time
     */
    public function getStateAt(\DateTimeInterface $dateTime): array
    {
        $events = AuditLog::where(function ($query) {
            $query->where('subject_type', static::class)
                  ->where('subject_id', $this->getKey());
        })->orWhere(function ($query) {
            $query->where('auditable_type', static::class)
                  ->where('auditable_id', $this->getKey());
        })
        ->where('created_at', '<=', $dateTime)
        ->orderBy('created_at')
        ->get();

        $state = [];
        
        foreach ($events as $event) {
            $action = explode(':', $event->action)[1] ?? $event->action;
            
            if ($action === 'created') {
                $state = $event->new_values ?? [];
            } elseif ($action === 'updated') {
                $state = array_merge($state, $event->new_values ?? []);
            } elseif ($action === 'deleted' || $action === 'force_deleted') {
                $state = ['_deleted' => true, '_deleted_at' => $event->created_at];
            } elseif ($action === 'restored') {
                unset($state['_deleted'], $state['_deleted_at']);
            }
        }
        
        return $state;
    }
}
