<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'title',
        'description',
        'file_name',
        'file_path',
        'file_size',
        'file_type',
        'mime_type',
        'folder',
        'category',
        'status',
        'version',
        'version_number',
        'metadata',
    ];

    protected $guarded = [
        'uploaded_by',
        'branch_id',
        'access_level',
        'is_public',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'version' => 'integer',
        'version_number' => 'integer',
        'is_public' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($document) {
            $document->code ??= Str::uuid()->toString();

            if (auth()->check()) {
                $user = auth()->user();

                if ($user?->branch_id && $document->branch_id && $document->branch_id !== $user->branch_id) {
                    $document->branch_id = $user->branch_id;
                }

                $document->branch_id ??= $user?->branch_id;
                $document->uploaded_by ??= $user?->id;
            }

            if (!$document->version) {
                $document->version = 1;
            }
            if (!$document->version_number) {
                $document->version_number = 1;
            }
            if (!$document->status) {
                $document->status = 'draft';
            }
        });
    }

    // Relationships
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DocumentTag::class, 'document_tag', 'document_id', 'document_tag_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DocumentShare::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DocumentActivity::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePublic($query)
    {
        return $query->where(function ($q) {
            $q->where('is_public', true)
                ->orWhere('access_level', 'public');
        });
    }

    public function scopeInFolder($query, string $folder)
    {
        return $query->where('folder', $folder);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // Business Methods
    public function getFileSizeFormatted(): string
    {
        $size = (int) ($this->file_size ?? 0);
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        for (; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    public function getDownloadUrl(): string
    {
        return route('app.documents.download', ['document' => $this->id]);
    }

    public function canBeAccessedBy(User $user): bool
    {
        // Public documents can be accessed by anyone
        if ($this->is_public || $this->access_level === 'public') {
            return true;
        }

        if (
            $this->branch_id
            && $user->branch_id
            && $this->branch_id !== $user->branch_id
        ) {
            return false;
        }

        // Owner can always access
        if ($this->uploaded_by === $user->id) {
            return true;
        }

        // Check if shared with user
        return $this->shares()
            ->where(function ($query) use ($user) {
                $query->where('shared_with_user_id', $user->id);
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function logActivity(string $action, ?User $user = null, ?array $details = null): void
    {
        $this->activities()->create([
            'action' => $action,
            'user_id' => $user?->id ?? auth()->id(),
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
