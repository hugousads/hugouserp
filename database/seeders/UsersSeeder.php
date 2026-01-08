<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@ghanem-lvju-egypt.com';

        // Get branch ID directly from database
        $branchId = \DB::table('branches')
            ->where('is_main', true)
            ->value('id');
        
        if (! $branchId) {
            $branchId = \DB::table('branches')->value('id');
        }

        // Check if user already exists using DB::table to avoid model issues
        $userId = \DB::table('users')->where('email', $email)->value('id');

        if (! $userId) {
            // Create user
            User::query()->create([
                'name' => 'Super Admin',
                'email' => $email,
                'password' => Hash::make('0150386787'),
                'phone' => '0150386787',
                'is_active' => true,
                'username' => 'admin',
                'locale' => 'en',
                'timezone' => config('app.timezone'),
                'branch_id' => $branchId,
            ]);
            
            // Get the ID of the created user
            $userId = \DB::table('users')->where('email', $email)->value('id');
        }

        if ($userId && $branchId) {
            // Sync branches using DB::table to avoid model issues
            $existing = \DB::table('branch_user')
                ->where('user_id', $userId)
                ->where('branch_id', $branchId)
                ->exists();
            
            if (! $existing) {
                \DB::table('branch_user')->insert([
                    'user_id' => $userId,
                    'branch_id' => $branchId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Assign role - use DB to get model_id to avoid the attribute issue
        $userId = \DB::table('users')->where('email', $email)->value('id');
        
        if (! $userId) {
            return;
        }

        $superAdminRoleId = \DB::table('roles')
            ->where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($superAdminRoleId) {
            // Check if role is already assigned
            $hasRole = \DB::table('model_has_roles')
                ->where('role_id', $superAdminRoleId)
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $userId)
                ->exists();
            
            if (! $hasRole) {
                \DB::table('model_has_roles')->insert([
                    'role_id' => $superAdminRoleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ]);
            }
        }
    }
}
