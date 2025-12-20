<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'name',
        'original_name',
        'file_path',
        'thumbnail_path',
        'mime_type',
        'extension',
        'size',
        'optimized_size',
        'width',
        'height',
        'disk',
        'collection',
        'user_id',
        'branch_id',
        'metadata',
    ];

    protected $casts = [
        'size' => 'integer',
        'optimized_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getUrlAttribute(): string
    {
        $disk = Storage::disk($this->disk);

        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($this->file_path, now()->addMinutes(10));
            } catch (\Exception) {
                // fallback below
            }
        }

        return $disk->url($this->file_path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }
        $disk = Storage::disk($this->disk);

        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($this->thumbnail_path, now()->addMinutes(10));
            } catch (\Exception) {
                // fallback below
            }
        }

        return $disk->url($this->thumbnail_path);
    }

    public function getHumanSizeAttribute(): string
    {
        return $this->formatBytes($this->size);
    }

    public function getOptimizedHumanSizeAttribute(): ?string
    {
        if (!$this->optimized_size) {
            return null;
        }
        return $this->formatBytes($this->optimized_size);
    }

    public function getCompressionRatioAttribute(): ?float
    {
        if (!$this->optimized_size || $this->size == 0) {
            return null;
        }
        return round((1 - ($this->optimized_size / $this->size)) * 100, 2);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isDocument(): bool
    {
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ];
        return in_array($this->mime_type, $documentTypes);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    public function scopeDocuments($query)
    {
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ];
        return $query->whereIn('mime_type', $documentTypes);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }
}
