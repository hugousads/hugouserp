<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordSeedingTest extends TestCase
{
    use RefreshDatabase;

    protected function seedBaseline(): void
    {
        $this->seed();
    }

    public function test_seeded_admin_can_login(): void
    {
        $this->seedBaseline();

        Event::fake();

        $authService = app(AuthService::class);

        $result = $authService->attemptMultiFieldLogin('admin@ghanem-lvju-egypt.com', '0150386787');

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['user']);
        $this->assertTrue(Hash::check('0150386787', $result['user']->password));
    }

    public function test_created_user_has_password_and_can_login(): void
    {
        $this->seedBaseline();

        Event::fake();

        $branchId = (int) DB::table('branches')->value('id');

        $userService = app(UserService::class);

        $user = $userService->createUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret123',
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        $this->assertNotEmpty($user->password);

        $authService = app(AuthService::class);
        $result = $authService->attemptMultiFieldLogin('test@example.com', 'secret123');

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['user']);
        $this->assertTrue(Hash::check('secret123', $result['user']->password));
    }
}
