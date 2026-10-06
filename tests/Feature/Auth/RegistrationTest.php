<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registrasi publik dimatikan untuk sistem internal.
 * Test ini mengunci perilaku tersebut agar tidak aktif kembali tanpa sengaja.
 *
 * @see dokumentasi/04-autentikasi.md §2
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_register_route_name_does_not_exist(): void
    {
        $this->assertFalse(app('router')->has('register'));
    }
}
