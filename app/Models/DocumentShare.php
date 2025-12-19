<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'shared_with_user_id',
        'shared_with_role',
        'shared_by',
        'permission',
        'expires_at',
        'access_count',
        'last_accessed_at',
        'password_hash',
        'notify_on_access',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
        'notify_on_access' => 'boolean',
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

    public function sharedWithUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    public function sharer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    // Business Methods
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function incrementAccessCount(): void
    {
        $this->increment('access_count');
        $this->last_accessed_at = now();
        $this->save();
    }

    public function canView(): bool
    {
        return in_array($this->permission, ['view', 'edit', 'full']);
    }

    public function canEdit(): bool
    {
        return in_array($this->permission, ['edit', 'full']);
    }

    public function canDelete(): bool
    {
        return $this->permission === 'full';
    }
}
