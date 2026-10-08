<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pencegahan tampilan form basi saat tombol Back browser (W2).
 *
 * Tanpa penanganan, menekan Back setelah menyimpan menampilkan kembali form
 * yang sudah terisi dari memori browser, sehingga menyimpan ulang
 * menghasilkan data DUPLIKAT.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md W2
 */
class PreventBackCacheTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Halaman form harus mengirim `no-store` agar browser membuang salinannya.
     */
    public function test_form_pages_send_no_store_header(): void
    {
        $admin = $this->admin();

        $formRoutes = [
            route('assets.create'),
            route('components.create'),
            route('departments.create'),
            route('employees.create'),
            route('users.create'),
            route('profile.edit'),
        ];

        foreach ($formRoutes as $url) {
            $response = $this->actingAs($admin)->get($url)->assertOk();

            $cacheControl = $response->headers->get('Cache-Control');

            $this->assertStringContainsString('no-store', $cacheControl, "Header no-store hilang di {$url}");
            $this->assertStringContainsString('no-cache', $cacheControl, "Header no-cache hilang di {$url}");
            $this->assertSame('no-cache', $response->headers->get('Pragma'));
            $this->assertSame('0', $response->headers->get('Expires'));
        }
    }

    public function test_edit_form_pages_send_no_store_header(): void
    {
        $admin = $this->admin();
        $asset = \App\Models\Asset::factory()->pc()->create();
        $component = \App\Models\Component::factory()->create();

        foreach ([route('assets.edit', $asset), route('components.edit', $component)] as $url) {
            $response = $this->actingAs($admin)->get($url)->assertOk();

            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'), "Header no-store hilang di {$url}");
        }
    }

    /**
     * Form input memakai autocomplete="off" agar Chrome tidak memulihkan
     * nilai lama saat tombol Back ditekan.
     */
    public function test_input_forms_disable_autocomplete(): void
    {
        $admin = $this->admin();

        $pages = [
            route('assets.create'),
            route('components.create'),
            route('departments.create'),
            route('employees.create'),
        ];

        foreach ($pages as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString(
                'autocomplete="off"',
                $html,
                "Form di {$url} tidak memakai autocomplete=off."
            );
        }
    }

    /**
     * Halaman daftar/read-only tidak perlu no-store agar navigasi tetap cepat.
     */
    public function test_list_pages_are_not_marked_no_store(): void
    {
        $response = $this->actingAs($this->admin())->get(route('assets.index'))->assertOk();

        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_middleware_alias_is_registered(): void
    {
        $this->assertArrayHasKey('prevent-back-cache', app('router')->getMiddleware());
    }
}
