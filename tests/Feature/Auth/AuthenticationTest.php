<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login memakai username (FB-1).
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_login_screen_asks_for_username_not_email(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertDontSee('name="email"', false);
    }

    public function test_users_can_authenticate_with_username(): void
    {
        $user = User::factory()->create(['username' => 'admin']);

        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_username_is_case_insensitive_on_login(): void
    {
        User::factory()->create(['username' => 'admin']);

        $this->post('/login', [
            'username' => 'ADMIN',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create(['username' => 'admin']);

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_password_reset_and_verification_routes_do_not_exist(): void
    {
        // Sistem internal tanpa email: jalur ini sengaja tidak ada.
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/verify-email')->assertNotFound();
        $this->get('/confirm-password')->assertNotFound();
    }
}
