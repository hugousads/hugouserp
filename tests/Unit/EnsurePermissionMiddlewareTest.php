<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\EnsurePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsurePermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected EnsurePermission $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsurePermission;
    }

    public function test_unauthenticated_web_request_aborts_with_401(): void
    {
        $request = Request::create('/admin/test', 'GET');
        $request->headers->set('Accept', 'text/html');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Unauthenticated');

        $this->middleware->handle($request, fn () => response('OK'), 'some.permission');
    }

    public function test_web_request_aborts_with_html_on_permission_denied(): void
    {
        $user = User::factory()->create();

        $request = Request::create('/admin/test', 'GET');
        $request->headers->set('Accept', 'text/html');
        $request->setUserResolver(fn () => $user);

        // Web requests should abort() which throws an HttpException
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('permission');

        $this->middleware->handle($request, fn () => response('OK'), 'missing.permission');
    }

    public function test_api_route_returns_json_on_permission_denied(): void
    {
        $user = User::factory()->create();

        // Using /api/* path triggers JSON response
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, fn () => response('OK'), 'missing.permission');

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $json = json_decode($response->getContent(), true);
        $this->assertFalse($json['success']);
        $this->assertStringContainsString('permission', strtolower($json['message']));
    }

    public function test_xhr_request_returns_json_on_permission_denied(): void
    {
        $user = User::factory()->create();

        // XHR requests also get JSON responses due to expectsJson()
        $request = Request::create('/admin/test', 'GET');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, fn () => response('OK'), 'missing.permission');

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function test_user_with_permission_can_access(): void
    {
        // Create permission and role
        $permission = Permission::findOrCreate('test.access', 'web');
        $role = Role::findOrCreate('Tester', 'web');
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, fn () => response('OK', 200), 'test.access');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_negated_permission_denies_user_with_permission(): void
    {
        // Create permission and assign to user
        $permission = Permission::findOrCreate('admin.delete', 'web');
        $role = Role::findOrCreate('Admin', 'web');
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        // Using /api/* path triggers JSON response
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(fn () => $user);

        // Negated permission: user MUST NOT have 'admin.delete'
        $response = $this->middleware->handle($request, fn () => response('OK'), '!admin.delete');

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function test_negated_permission_allows_user_without_permission(): void
    {
        $user = User::factory()->create();

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);

        // Negated permission: user MUST NOT have 'restricted.action'
        $response = $this->middleware->handle($request, fn () => response('OK', 200), '!restricted.action');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_multiple_permissions_with_any_mode(): void
    {
        // Create permissions and assign only one to user
        Permission::findOrCreate('action.a', 'web');
        $permission = Permission::findOrCreate('action.b', 'web');
        $role = Role::findOrCreate('PartialRole', 'web');
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $user);

        // User has action.b but not action.a - ANY mode should pass
        $response = $this->middleware->handle($request, fn () => response('OK', 200), 'action.a|action.b');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_multiple_permissions_with_all_mode(): void
    {
        // Create permissions and assign only one to user
        Permission::findOrCreate('action.a', 'web');
        $permission = Permission::findOrCreate('action.b', 'web');
        $role = Role::findOrCreate('PartialRole', 'web');
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $user);

        // User has action.b but not action.a - ALL mode should fail
        $response = $this->middleware->handle($request, fn () => response('OK'), 'action.a&action.b');

        $this->assertEquals(403, $response->getStatusCode());
    }
}
