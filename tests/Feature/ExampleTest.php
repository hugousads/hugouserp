<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Test that unauthenticated users are redirected to login with intended URL.
     * 
     * NOTE: This test does not use RefreshDatabase because it only tests
     * the authentication redirect behavior and does not persist any data.
     */
    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        // Re-enable exception handling for this test since we're testing redirects
        $this->withExceptionHandling();

        // Test protected route redirect
        $response = $this->get('/dashboard');

        // Assert guest status before redirect
        $this->assertGuest();

        // Expect redirect to login for unauthenticated users
        $response->assertStatus(302);
        $response->assertRedirectToRoute('login');

        // Assert intended URL is stored in session
        $this->assertEquals(url('/dashboard'), session('url.intended'));

        // Follow the redirect to the login page
        $loginResponse = $this->get($response->headers->get('Location'));

        // Assert the login page content is rendered
        $loginResponse->assertStatus(200);
        
        // Check for Livewire login component
        $loginResponse->assertSeeLivewire('auth.login');

        // Confirm the guard remains unauthenticated after following redirect
        $this->assertGuest();
    }

    /**
     * Test that JSON requests return 401 Unauthenticated
     */
    public function test_unauthenticated_json_requests_return_401(): void
    {
        // Re-enable exception handling for this test since we're testing 401 responses
        $this->withExceptionHandling();

        $response = $this->getJson('/dashboard');

        $this->assertGuest();
        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    }

    /**
     * Test that login page renders Livewire login component
     */
    public function test_login_page_renders_livewire_component(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        // Check for Livewire component presence
        $response->assertSeeLivewire('auth.login');
    }
}
