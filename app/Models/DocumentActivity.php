<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class DocumentActivity extends Model
{
    use HasFactory;

    protected $table = 'document_activities';

    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'user_id',
        'action',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Relationships
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // Business Methods
    public function getActionLabel(): string
    {
        return match($this->action) {
            'created' => __('Created'),
            'viewed' => __('Viewed'),
            'downloaded' => __('Downloaded'),
            'edited' => __('Edited'),
            'deleted' => __('Deleted'),
            'shared' => __('Shared'),
            'unshared' => __('Unshared'),
            'restored' => __('Restored'),
            'version_created' => __('New version created'),
            default => __(ucwords(str_replace('_', ' ', $this->action))),
        };
    }
}
