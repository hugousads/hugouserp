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

        /** @var User|null $existing */
        $existing = User::query()->where('email', $email)->first();

        /** @var Branch|null $branch */
        $branch = Branch::query()->where('is_main', true)->first() ?? Branch::query()->first();

        if (! $existing) {
            $existing = User::query()->create([
                'name' => 'Super Admin',
                'email' => $email,
                'password' => Hash::make('0150386787'),
                'phone' => '0150386787',
                'is_active' => true,
                'username' => 'admin',
                'locale' => 'en',
                'timezone' => config('app.timezone'),
                'branch_id' => $branch?->id,
            ]);
        }

        if ($branch && method_exists($existing, 'branches')) {
            $existing->branches()->syncWithoutDetaching([$branch->id]);
        }

        /** @var Role|null $superAdmin */
        $superAdmin = Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->first();

        if ($superAdmin && method_exists($existing, 'assignRole')) {
            if (! $existing->hasRole($superAdmin->name)) {
                $existing->assignRole($superAdmin);
            }
        }
    }
}
