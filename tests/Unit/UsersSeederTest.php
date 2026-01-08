<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Database\Seeders\UsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsersSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup required data for seeder
        $this->setupSeederRequirements();
    }

    protected function setupSeederRequirements(): void
    {
        // Create a branch first (required by seeder)
        DB::table('branches')->insert([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create Super Admin role (required by seeder)
        DB::table('roles')->insert([
            'name' => 'Super Admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_users_seeder_sets_password_correctly(): void
    {
        // Run the seeder
        $seeder = new UsersSeeder();
        $seeder->run();

        // Find the created user
        $user = User::where('email', 'admin@ghanem-lvju-egypt.com')->first();

        // Assert user was created
        $this->assertNotNull($user);
        
        // Get password from database directly since it's hidden
        $userWithPassword = DB::table('users')
            ->where('email', 'admin@ghanem-lvju-egypt.com')
            ->first();
        
        // Assert password is set and is not null
        $this->assertNotNull($userWithPassword->password);
        $this->assertNotEmpty($userWithPassword->password);

        // Assert the password can be verified with the correct password
        $this->assertTrue(
            Hash::check('0150386787', $userWithPassword->password),
            'The seeded user password should match the expected password'
        );

        // Assert user attributes are correct
        $this->assertEquals('Super Admin', $user->name);
        $this->assertEquals('admin@ghanem-lvju-egypt.com', $user->email);
        $this->assertEquals('0150386787', $user->phone);
        $this->assertEquals('admin', $user->username);
        $this->assertTrue($user->is_active);
    }

    public function test_users_seeder_does_not_duplicate_user(): void
    {
        // Run the seeder twice
        $seeder = new UsersSeeder();
        $seeder->run();
        $seeder->run();

        // Assert only one user exists with this email
        $userCount = User::where('email', 'admin@ghanem-lvju-egypt.com')->count();
        $this->assertEquals(1, $userCount, 'Seeder should not create duplicate users');
    }
}
