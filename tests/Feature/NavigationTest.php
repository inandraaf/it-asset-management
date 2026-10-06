<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Menu tulis hanya boleh tampil untuk Admin IT.
 *
 * Route modul didaftarkan di sini agar tautan navigasi yang bergantung pada
 * Route::has() dapat dirender, tanpa menunggu milestone M3/M4.
 *
 * Catatan: setelah mendaftarkan route secara runtime, name lookup pada
 * RouteCollection perlu di-refresh. Route::has() di dalam request akan
 * membaca lookup tersebut.
 *
 * @see dokumentasi/09-routing-dan-otorisasi.md §4
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/__test/assets', fn () => '')->name('assets.index');
        Route::middleware('web')->get('/__test/assets/create', fn () => '')->name('assets.create');
        Route::middleware('web')->get('/__test/employees', fn () => '')->name('employees.index');
        Route::middleware('web')->get('/__test/departments', fn () => '')->name('departments.index');

        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_admin_sees_create_asset_link(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Tambah Aset');
    }

    public function test_viewer_does_not_see_create_asset_link(): void
    {
        $this->actingAs(User::factory()->viewer()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Tambah Aset');
    }

    public function test_both_roles_see_read_only_menu_links(): void
    {
        // Assert href tautan asli, bukan substring "Aset", karena "Tambah Aset"
        // (khusus admin) juga mengandung kata "Aset".
        foreach (['admin', 'viewer'] as $state) {
            $this->actingAs(User::factory()->{$state}()->create())
                ->get('/dashboard')
                ->assertOk()
                ->assertSee('href="'.route('assets.index').'"', false)
                ->assertSee('href="'.route('employees.index').'"', false)
                ->assertSee('href="'.route('departments.index').'"', false);
        }
    }
}
