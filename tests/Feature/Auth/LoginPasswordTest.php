<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\User;
use App\Services\AuthService;
use App\Services\UserService;
use Database\Seeders\UsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_after_seeding(): void
    {
        Branch::factory()->main()->create();
        Role::findOrCreate('Super Admin', 'web');

        $this->seed(UsersSeeder::class);

        $admin = User::where('email', 'admin@ghanem-lvju-egypt.com')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($admin->password);
        $this->assertTrue(Hash::check('0150386787', $admin->password));

        $result = app(AuthService::class)->attemptMultiFieldLogin('admin@ghanem-lvju-egypt.com', '0150386787');

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['user']);
        $this->assertSame($admin->id, $result['user']->id);
    }

    public function test_user_created_via_service_can_login(): void
    {
        $branch = Branch::factory()->main()->create();
        $password = 'secret123';

        $user = app(UserService::class)->createUser([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => $password,
            'branch_id' => $branch->id,
        ]);

        $this->assertNotNull($user->password);
        $this->assertTrue(Hash::check($password, $user->password));

        $result = app(AuthService::class)->attemptMultiFieldLogin($user->email, $password);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['user']);
        $this->assertSame($user->id, $result['user']->id);
    }
}
