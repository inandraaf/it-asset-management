<?php

namespace Tests\Feature;

use App\Enums\ComponentCategory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression untuk bug UI yang ditemukan saat uji browser (R5).
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md R5
 */
class UiRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `style="display: none;"` inline pada dropdown bertabrakan dengan x-show:
     * Alpine men-capture display awal sebagai "none" sehingga menu tidak pernah
     * tampil walau state `open` bernilai true.
     */
    public function test_dropdown_menu_does_not_use_inline_display_none(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        // Elemen menu dropdown harus memakai x-cloak, bukan style inline.
        $this->assertStringNotContainsString(
            'style="display: none;"',
            $html,
            'Dropdown memakai style display:none inline yang mematikan x-show.'
        );

        $this->assertStringContainsString('x-cloak', $html);
    }

    /**
     * Bundle JS harus benar-benar memuat (Chart terimpor). Bila `Chart` tidak
     * diimpor, bundle melempar "Chart is not defined" dan mematikan Alpine —
     * akibatnya dropdown & filter tidak jalan sama sekali.
     */
    public function test_js_bundle_is_built_and_contains_chart(): void
    {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);

        $this->assertArrayHasKey('resources/js/app.js', $manifest);

        $jsPath = public_path('build/'.$manifest['resources/js/app.js']['file']);
        $this->assertFileExists($jsPath);

        $js = file_get_contents($jsPath);

        // Chart.js ter-include (ukuran mencerminkan library ikut ter-bundle).
        $this->assertGreaterThan(200_000, strlen($js), 'Bundle JS terlalu kecil — Chart.js mungkin tidak ter-include.');
        $this->assertStringContainsString('initItamChart', $js);
    }

    /**
     * Opsi <select> untuk filter dirender langsung (bukan via <template x-for>
     * yang tidak didukung browser di dalam <select>).
     */
    public function test_filter_options_are_rendered_as_real_options(): void
    {
        // Opsi filter diambil dari komponen yang benar-benar terpasang,
        // jadi komponennya harus dipasang ke sebuah aset lebih dulu.
        $asset = Asset::factory()->create();
        $ram = Component::factory()->ofCategory(ComponentCategory::Ram)->create([
            'specs' => ['capacity' => '16GB', 'type' => 'DDR4'],
        ]);
        \App\Models\ComponentInstallation::factory()->create([
            'component_id' => $ram->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('assets.index'))
            ->assertOk()
            ->getContent();

        // <template x-for> di dalam <select> diabaikan browser.
        $this->assertStringNotContainsString('<template x-for', $html);

        // Nilai filter benar-benar ada sebagai <option>.
        $this->assertStringContainsString('value="16GB"', $html);
        $this->assertStringContainsString('id="filter_category"', $html);
        $this->assertStringContainsString('id="filter_attribute"', $html);
        $this->assertStringContainsString('id="component_value"', $html);
    }

    /**
     * Kedua <select> WAJIB berada dalam SATU wrapper x-data. Bila x-data
     * dipasang pada masing-masing <div>, state tidak terbagi dan pilihan
     * Nilai tidak pernah tersaring (R6).
     */
    public function test_filter_dropdowns_share_one_alpine_scope(): void
    {
        $asset = Asset::factory()->create();
        $ram = Component::factory()->ofCategory(ComponentCategory::Ram)->create([
            'specs' => ['capacity' => '8GB', 'type' => 'DDR4'],
        ]);
        \App\Models\ComponentInstallation::factory()->create([
            'component_id' => $ram->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('assets.index'))
            ->assertOk()
            ->getContent();

        // Ambil rentang antara select kategori dan select nilai.
        $catPos = strpos($html, 'id="filter_category"');
        $valPos = strpos($html, 'id="component_value"');
        $this->assertNotFalse($catPos);
        $this->assertNotFalse($valPos);

        // Tidak boleh ada x-data BARU di antara kedua select (itu tanda
        // masing-masing punya scope sendiri).
        $between = substr($html, $catPos, $valPos - $catPos);
        $this->assertSame(
            0,
            substr_count($between, 'x-data'),
            'Select Nilai berada di luar scope x-data kategori/atribut.'
        );

        // Harus ada x-data SEBELUM select kategori (wrapper bersama).
        $before = substr($html, 0, $catPos);
        $this->assertStringContainsString('x-data', $before);

        // Nilai disaring dengan atribut hidden (bukan x-for).
        $this->assertStringContainsString(':hidden=', $html);
    }

    public function test_filter_dropdowns_are_side_by_side(): void
    {
        Component::factory()->ofCategory(ComponentCategory::Ram)->create([
            'specs' => ['capacity' => '8GB', 'type' => 'DDR4'],
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('assets.index'))
            ->assertOk()
            ->getContent();

        // Wrapper filter memakai dua kolom; tidak ada pemisah baris mt-4.
        $this->assertStringContainsString('lg:col-span-2', $html);
        $this->assertStringNotContainsString('class="mt-4"', $html);
    }

    // -------------------------------------------------- R6 Tooltip & Alert

    /**
     * Tooltip kolom spesifikasi harus menampilkan daftar BERLABEL, bukan
     * mengulang ringkasan yang sama.
     */
    public function test_hardware_tooltip_labels_each_component(): void
    {
        $asset = Asset::factory()->create();
        $ram = Component::factory()->ofCategory(ComponentCategory::Ram)->create([
            'specs' => ['capacity' => '16GB', 'type' => 'DDR4'],
        ]);
        $cpu = Component::factory()->ofCategory(ComponentCategory::Cpu)->create([
            'specs' => ['series' => 'i7-11700'],
        ]);

        foreach ([$ram, $cpu] as $component) {
            \App\Models\ComponentInstallation::factory()->create([
                'component_id' => $component->id,
                'asset_id' => $asset->id,
                'removed_date' => null,
            ]);
        }

        $asset->load('activeComponentInstallations.component');

        $detail = $asset->hardwareSummaryDetailed();

        $this->assertStringContainsString('RAM:', $detail);
        $this->assertStringContainsString('CPU:', $detail);
        $this->assertStringContainsString('16GB DDR4', $detail);

        // Berbeda dari ringkasan biasa (yang tanpa label).
        $this->assertNotSame($asset->hardwareSummary(), $detail);
    }

    public function test_asset_index_tooltip_contains_category_labels(): void
    {
        $asset = Asset::factory()->create();
        $ram = Component::factory()->ofCategory(ComponentCategory::Ram)->create([
            'specs' => ['capacity' => '16GB', 'type' => 'DDR4'],
        ]);
        \App\Models\ComponentInstallation::factory()->create([
            'component_id' => $ram->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('assets.index'))
            ->assertOk()
            ->getContent();

        // Tooltip (aria-label) memuat label kategori, bukan hanya nilai mentah.
        // X1: `title` bawaan browser diganti tooltip Alpine, jadi diperiksa
        // lewat `aria-label` yang isinya sama.
        $this->assertMatchesRegularExpression('/aria-label="[^"]*RAM:[^"]*"/', $html);
    }

    /**
     * Ringkasan spesifikasi memakai tooltip instan, bukan `title` bawaan.
     */
    public function test_asset_index_uses_instant_tooltip(): void
    {
        Asset::factory()->create();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('assets.index'))
            ->assertOk()
            ->getContent();

        // Tidak ada lagi `title` bawaan pada sel spesifikasi.
        $this->assertStringNotContainsString('title="Belum ada komponen terpasang."', $html);
        // Komponen tooltip Alpine dirender.
        $this->assertStringContainsString('itamTooltip()', $html);
        $this->assertStringContainsString('x-teleport="body"', $html);
    }

    /**
     * Konfirmasi memakai SweetAlert2, bukan confirm() bawaan browser.
     */
    public function test_destructive_actions_use_sweetalert_not_native_confirm(): void
    {
        $asset = Asset::factory()->create();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('assets.index'))
            ->assertOk()
            ->getContent();

        // Tidak ada lagi confirm() bawaan.
        $this->assertStringNotContainsString('onsubmit="return confirm', $html);

        // Form hapus memakai data-confirm.
        $this->assertStringContainsString('data-confirm=', $html);
        $this->assertStringContainsString('data-confirm-button=', $html);
    }

    public function test_all_destructive_forms_use_data_confirm(): void
    {
        $asset = Asset::factory()->create();
        $viewer = User::factory()->admin()->create();

        $pages = [
            route('assets.index'),
            route('assets.show', $asset),
            route('employees.index'),
            route('departments.index'),
            route('components.index'),
        ];

        foreach ($pages as $url) {
            $html = $this->actingAs($viewer)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('onsubmit="return confirm', $html, "Masih ada confirm() di {$url}");
        }
    }

    public function test_js_bundle_includes_sweetalert(): void
    {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $jsPath = public_path('build/'.$manifest['resources/js/app.js']['file']);
        $js = file_get_contents($jsPath);

        $this->assertStringContainsString('itamConfirm', $js);
        // SweetAlert2 menambah ukuran bundle secara signifikan.
        $this->assertGreaterThan(300_000, strlen($js), 'Bundle terlalu kecil — SweetAlert2 mungkin tidak ter-include.');
    }
}
