<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentActivity extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'user_id',
        'action',
        'details',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'details' => 'array',
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
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByUser($query, int $userId)
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
            'updated' => __('Updated'),
            'deleted' => __('Deleted'),
            'shared' => __('Shared'),
            'unshared' => __('Unshared'),
            'version_created' => __('New version created'),
            default => __(ucfirst($this->action)),
        };
    }
}
