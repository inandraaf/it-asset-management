<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Menguji middleware role pada route tulis.
 *
 * Route nyata untuk aset/karyawan/departemen baru ada pada milestone M3/M4,
 * jadi di sini route uji didaftarkan dengan middleware yang sama agar
 * perilaku `role:admin` terverifikasi lebih dulu.
 *
 * @see dokumentasi/04-autentikasi.md §4 dan §7
 */
class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:admin'])
            ->post('/__test/admin-write', fn () => response('ok'))
            ->name('test.admin-write');

        Route::middleware(['web', 'auth', 'role:admin,viewer'])
            ->get('/__test/any-role', fn () => response('ok'))
            ->name('test.any-role');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post('/__test/admin-write')->assertRedirect(route('login'));
    }

    public function test_admin_can_access_admin_only_route(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/__test/admin-write')
            ->assertOk();
    }

    public function test_viewer_is_forbidden_from_admin_only_route(): void
    {
        $this->actingAs(User::factory()->viewer()->create())
            ->post('/__test/admin-write')
            ->assertForbidden();
    }

    public function test_viewer_can_access_route_that_allows_multiple_roles(): void
    {
        $this->actingAs(User::factory()->viewer()->create())
            ->get('/__test/any-role')
            ->assertOk();
    }

    public function test_role_alias_is_registered(): void
    {
        $this->assertArrayHasKey('role', app('router')->getMiddleware());
    }
}
