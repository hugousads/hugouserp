<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ProjectMilestone extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'due_date',
        'status',
        'progress',
        'deliverables',
        'achieved_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'achieved_date' => 'datetime',
        'progress' => 'integer',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeAchieved(Builder $query): Builder
    {
        return $query->where('status', 'achieved');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', now())
                    ->where('status', 'pending');
    }

    // Business Methods
    public function markAsAchieved(): bool
    {
        $this->status = 'achieved';
        $this->achieved_date = now();
        $this->progress = 100;

        return $this->save();
    }

    public function markAsMissed(): bool
    {
        $this->status = 'missed';

        return $this->save();
    }

    public function isOverdue(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        return $this->due_date && $this->due_date->isPast();
    }

    public function canBeAchieved(): bool
    {
        return $this->status === 'pending';
    }
}
