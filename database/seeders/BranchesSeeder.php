<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchesSeeder extends Seeder
{
    public function run(): void
    {
        if (Branch::query()->count() > 0) {
            return;
        }

        Branch::query()->create([
            'name' => 'Main Branch',
            'name_ar' => 'الفرع الرئيسي',
            'code' => 'MAIN',
            'is_active' => true,
            'address' => 'Head Office',
            'phone' => '01000000000',
            'timezone' => config('app.timezone'),
            'currency' => 'EGP',
            'is_main' => true,
            'settings' => [],
        ]);
    }
}
