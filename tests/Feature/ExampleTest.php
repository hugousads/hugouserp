<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Test that unauthenticated users are redirected to login.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // Expect redirect to login for unauthenticated users
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }
}
