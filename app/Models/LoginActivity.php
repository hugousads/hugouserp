<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginActivity extends Model
{
    protected $table = 'login_activities';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'email',
        'event',
        'ip_address',
        'user_agent',
        'browser',
        'platform',
        'device_type',
        'failure_reason',
        'status',
        'metadata',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function logLogin($user, string $ip, string $userAgent): self
    {
        $parsed = self::parseUserAgent($userAgent);

        return self::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'event' => 'login',
            'status' => 'success',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'browser' => $parsed['browser'] ?? 'Unknown',
            'platform' => $parsed['platform'] ?? 'Unknown',
            'device_type' => $parsed['device_type'] ?? 'Desktop',
        ]);
    }

    public static function logLogout($user, string $ip): self
    {
        return self::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'event' => 'logout',
            'status' => 'success',
            'ip_address' => $ip,
        ]);
    }

    public static function logFailedAttempt(string $email, string $ip, string $userAgent, ?string $reason = null): self
    {
        $parsed = self::parseUserAgent($userAgent);

        return self::create([
            'email' => $email,
            'event' => 'failed',
            'status' => 'failed',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'browser' => $parsed['browser'] ?? 'Unknown',
            'platform' => $parsed['platform'] ?? 'Unknown',
            'device_type' => $parsed['device_type'] ?? 'Desktop',
            'failure_reason' => $reason,
        ]);
    }

    protected static function parseUserAgent(string $userAgent): array
    {
        $browser = 'Unknown';
        $platform = 'Unknown';
        $deviceType = 'Desktop';

        if (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
            $browser = 'Opera';
        }

        if (preg_match('/Windows/i', $userAgent)) {
            $platform = 'Windows';
        } elseif (preg_match('/Mac/i', $userAgent)) {
            $platform = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $platform = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $platform = 'Android';
            $deviceType = 'Mobile';
        } elseif (preg_match('/iPhone|iPad/i', $userAgent)) {
            $platform = 'iOS';
            $deviceType = 'Mobile';
        }

        if (preg_match('/Mobile/i', $userAgent)) {
            $deviceType = 'Mobile';
        } elseif (preg_match('/Tablet/i', $userAgent)) {
            $deviceType = 'Tablet';
        }

        return [
            'browser' => $browser,
            'platform' => $platform,
            'device_type' => $deviceType,
        ];
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeLogins(Builder $query): Builder
    {
        return $query->where('event', 'login');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('event', 'failed');
    }
}
