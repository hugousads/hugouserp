<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketPriority extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'level',
        'color',
        'response_time_hours',
        'resolution_time_hours',
        'response_time_minutes',
        'resolution_time_minutes',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'level' => 'integer',
        'response_time_hours' => 'integer',
        'resolution_time_hours' => 'integer',
        'response_time_minutes' => 'integer',
        'resolution_time_minutes' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'priority_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('level');
    }

    // Business Methods
    public function getResponseTimeFormatted(): string
    {
        if (!$this->response_time_minutes) {
            return 'N/A';
        }

        $hours = floor($this->response_time_minutes / 60);
        $minutes = $this->response_time_minutes % 60;

        if ($hours > 0) {
            return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
        }

        return "{$minutes}m";
    }

    public function getResolutionTimeFormatted(): string
    {
        if (!$this->resolution_time_minutes) {
            return 'N/A';
        }

        $hours = floor($this->resolution_time_minutes / 60);
        $minutes = $this->resolution_time_minutes % 60;

        if ($hours > 24) {
            $days = floor($hours / 24);
            $remainingHours = $hours % 24;
            return $remainingHours > 0 ? "{$days}d {$remainingHours}h" : "{$days}d";
        }

        if ($hours > 0) {
            return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
        }

        return "{$minutes}m";
    }

    public function isCritical(): bool
    {
        return $this->level >= 4;
    }

    public function isHigh(): bool
    {
        return $this->level === 3;
    }

    public function isMedium(): bool
    {
        return $this->level === 2;
    }

    public function isLow(): bool
    {
        return $this->level === 1;
    }

    // Helper methods for converting between units
    public function getResponseTimeInMinutes(): int
    {
        return $this->response_time_minutes ?? (($this->response_time_hours ?? 0) * 60);
    }

    public function getResolutionTimeInMinutes(): int
    {
        return $this->resolution_time_minutes ?? (($this->resolution_time_hours ?? 0) * 60);
    }
}
