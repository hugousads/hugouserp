<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowDefinition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'module_name',
        'entity_type',
        'description',
        'stages',
        'rules',
        'is_active',
        'is_mandatory',
        'created_by',
    ];

    protected $casts = [
        'stages' => 'array',
        'rules' => 'array',
        'is_active' => 'boolean',
        'is_mandatory' => 'boolean',
    ];

    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    public function workflowRules(): HasMany
    {
        return $this->hasMany(WorkflowRule::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForModule($query, string $moduleName)
    {
        return $query->where('module_name', $moduleName);
    }

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Get ordered stages
     */
    public function getOrderedStages(): array
    {
        $stages = $this->stages ?? [];
        usort($stages, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        return $stages;
    }

    /**
     * Get stage by name
     */
    public function getStage(string $stageName): ?array
    {
        foreach ($this->stages ?? [] as $stage) {
            if ($stage['name'] === $stageName) {
                return $stage;
            }
        }

        return null;
    }

    /**
     * Get next stage
     */
    public function getNextStage(string $currentStageName): ?array
    {
        $stages = $this->getOrderedStages();
        $currentStageIndex = null;

        foreach ($stages as $index => $stage) {
            if ($stage['name'] === $currentStageName) {
                $currentStageIndex = $index;
                break;
            }
        }

        if ($currentStageIndex !== null && isset($stages[$currentStageIndex + 1])) {
            return $stages[$currentStageIndex + 1];
        }

        return null;
    }
}
