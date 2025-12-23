<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Default guard for API-based auth.
     */
    protected $guard_name = 'web';
}
