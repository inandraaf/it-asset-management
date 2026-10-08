<?php

namespace Tests\Feature;

use App\Enums\AssetType;
use App\Enums\ComponentCategory;
use App\Enums\ComponentStatus;
use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentInstallation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Temuan lanjutan (T1–T7) — dokumentasi/15-feedback-dan-tindak-lanjut.md.
 */
class RefinementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function install(Asset $asset, ComponentCategory $category, array $specs): Component
    {
        $component = Component::factory()->ofCategory($category)->create([
            'specs' => $specs,
            'status' => ComponentStatus::Installed,
        ]);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        return $component;
    }

    // ------------------------------------------------------------- T1 CCTV

    public function test_cctv_has_no_owner_at_all(): void
    {
        $this->assertFalse(AssetType::Cctv->requiresDepartment());
        $this->assertFalse(AssetType::Cctv->isAssignable());
    }

    public function test_printer_requires_department_but_is_not_assignable(): void
    {
        $this->assertTrue(AssetType::Printer->requiresDepartment());
        $this->assertFalse(AssetType::Printer->isAssignable());
    }

    // -------------------------------------------------------- T2 Ringkasan

    public function test_summary_follows_fixed_category_order(): void
    {
        $asset = Asset::factory()->create();

        // Sengaja dibuat urutan terbalik + kategori non-prioritas.
        $this->install($asset, ComponentCategory::Monitor, ['size' => '24"']);
        $this->install($asset, ComponentCategory::Storage, ['capacity' => '512GB', 'type' => 'SSD']);
        $this->install($asset, ComponentCategory::Ram, ['capacity' => '16GB', 'type' => 'DDR4']);
        $this->install($asset, ComponentCategory::Motherboard, ['chipset' => 'H510']);
        $this->install($asset, ComponentCategory::Cpu, ['series' => 'i7-11700']);

        $asset->load('activeComponentInstallations.component');

        // Urutan tetap: motherboard, CPU, RAM, storage. Monitor mengisi slot sisa.
        $this->assertSame('H510 · i7-11700 · 16GB DDR4 · 512GB SSD · 24"', $asset->hardwareSummary(5));
    }

    public function test_summary_fills_with_other_components_when_priority_missing(): void
    {
        $asset = Asset::factory()->create();

        // Tidak ada motherboard/CPU/RAM/storage/GPU.
        $this->install($asset, ComponentCategory::Monitor, ['size' => '27"']);
        $this->install($asset, ComponentCategory::Psu, ['wattage' => '500W']);

        $asset->load('activeComponentInstallations.component');

        $this->assertSame('27" · 500W', $asset->hardwareSummary());
    }

    public function test_summary_merges_identical_values(): void
    {
        $asset = Asset::factory()->create();
        $this->install($asset, ComponentCategory::Ram, ['capacity' => '8GB', 'type' => 'DDR4']);
        $this->install($asset, ComponentCategory::Ram, ['capacity' => '8GB', 'type' => 'DDR4']);

        $asset->load('activeComponentInstallations.component');

        $this->assertSame('8GB DDR4 x2', $asset->hardwareSummary());
    }

    // -------------------------------------------------------------- T3 OS

    public function test_os_appears_in_asset_info_card(): void
    {
        $asset = Asset::factory()->create(['specs' => ['os' => 'Windows 11 Pro']]);

        $this->actingAs($this->admin())
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Informasi Aset')
            ->assertSee('Sistem Operasi')
            ->assertSee('Windows 11 Pro');
    }

    // ----------------------------------------------------------- T4 Rakitan

    public function test_brand_is_optional_for_assembled_pc(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), [
                'type' => AssetType::PC->value,
                'brand' => null,
                'mac_address' => 'AA:BB:CC:DD:EE:01',
                'specs' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(Asset::sole()->brand);
    }

    public function test_assembled_pc_shows_rakitan_label(): void
    {
        $asset = Asset::factory()->create(['brand' => null]);

        $this->assertSame('Rakitan', $asset->brandLabel());

        $this->actingAs($this->admin())
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Rakitan');
    }

    // ---------------------------------------------------------- T6 Sidebar

    public function test_sidebar_has_three_groups_for_admin(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Menu')
            ->assertSee('Aksi Cepat')
            ->assertSee('Arsip');
    }

    public function test_viewer_sees_menu_but_not_admin_groups(): void
    {
        $this->actingAs(User::factory()->viewer()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Menu')
            ->assertDontSee('Aksi Cepat')
            ->assertDontSee('Arsip');
    }

    // ------------------------------------------- T7/R2 Filter per kategori

    public function test_filter_tree_groups_category_then_attribute(): void
    {
        $asset = Asset::factory()->create();
        $ram = Component::factory()->ofCategory(ComponentCategory::Ram)->create(['specs' => ['capacity' => '16GB', 'type' => 'DDR4']]);
        $disk = Component::factory()->ofCategory(ComponentCategory::Storage)->create(['specs' => ['capacity' => '512GB', 'type' => 'SSD']]);
        $cpu = Component::factory()->ofCategory(ComponentCategory::Cpu)->create(['specs' => ['series' => 'i7-11700']]);

        foreach ([$ram, $disk, $cpu] as $c) {
            \App\Models\ComponentInstallation::factory()->create([
                'component_id' => $c->id, 'asset_id' => $asset->id, 'removed_date' => null,
            ]);
        }

        $tree = Component::filterTree();

        // Storage hanya muncul SEKALI sebagai kategori (V1).
        $this->assertSame(
            ['ram', 'storage', 'cpu'],
            array_values(array_intersect(array_keys($tree), ['ram', 'storage', 'cpu']))
        );

        // Storage punya dua atribut: Tipe & Kapasitas Total.
        $this->assertArrayHasKey('type', $tree['storage']['attributes']);
        $this->assertArrayHasKey('total', $tree['storage']['attributes']);
        $this->assertSame(['SSD'], $tree['storage']['attributes']['type']['values']);
        $this->assertSame(['512GB'], $tree['storage']['attributes']['total']['values']);

        // RAM memakai atribut 'total'.
        $this->assertArrayHasKey('total', $tree['ram']['attributes']);
    }

    public function test_filter_tree_has_no_per_module_storage_option(): void
    {
        // 'per keping' dihapus karena ambigu (SSD atau HDD?).
        $this->assertArrayNotHasKey('module', Component::FILTER_GROUPS['storage']['attributes']);
    }

    /**
     * U1: selain per keping, tersedia filter kapasitas TOTAL storage
     * (SSD 512GB + HDD 1TB = 1536GB → ditampilkan 1536GB).
     */
    public function test_storage_capacity_total_filter_sums_all_modules(): void
    {
        $mixed = Asset::factory()->create(['asset_code' => 'PC-MIXED']);
        $only = Asset::factory()->create(['asset_code' => 'PC-ONLY512']);

        // SSD 512GB + HDD 1TB = total 1536GB.
        $this->install($mixed, ComponentCategory::Storage, ['capacity' => '512GB', 'type' => 'SSD']);
        $this->install($mixed, ComponentCategory::Storage, ['capacity' => '1TB', 'type' => 'HDD']);
        $this->install($only, ComponentCategory::Storage, ['capacity' => '512GB', 'type' => 'SSD']);

        // Opsi total storage berisi 512GB (per aset) dan 1536GB (gabungan).
        $this->assertContains('1536GB', Component::totalCapacityOptions('storage'));
        $this->assertContains('512GB', Component::totalCapacityOptions('storage'));

        // Filter total 1536GB hanya menemukan aset gabungan.
        $this->actingAs($this->admin())
            ->get(route('assets.index', ['filter_category' => 'storage', 'filter_attribute' => 'total', 'component_value' => '1536GB']))
            ->assertOk()
            ->assertSee('PC-MIXED')
            ->assertDontSee('PC-ONLY512');
    }

    public function test_storage_capacity_filter_matches_per_module(): void
    {
        $ssd = Asset::factory()->create(['asset_code' => 'PC-SSD512']);
        $hdd = Asset::factory()->create(['asset_code' => 'PC-HDD1TB']);

        $this->install($ssd, ComponentCategory::Storage, ['capacity' => '512GB', 'type' => 'SSD']);
        $this->install($hdd, ComponentCategory::Storage, ['capacity' => '1TB', 'type' => 'HDD']);

        // Filter kapasitas storage (per keping).
        $this->actingAs($this->admin())
            ->get(route('assets.index', ['filter_category' => 'storage', 'filter_attribute' => 'total', 'component_value' => '512GB']))
            ->assertOk()
            ->assertSee('PC-SSD512')
            ->assertDontSee('PC-HDD1TB');
    }

    public function test_ram_filter_uses_total_across_modules(): void
    {
        // Aset A: 2 keping 8GB (total 16GB). Aset B: 1 keping 8GB (total 8GB).
        $asetA = Asset::factory()->create(['asset_code' => 'PC-16TOTAL']);
        $asetB = Asset::factory()->create(['asset_code' => 'PC-8ONLY']);

        $this->install($asetA, ComponentCategory::Ram, ['capacity' => '8GB', 'type' => 'DDR4']);
        $this->install($asetA, ComponentCategory::Ram, ['capacity' => '8GB', 'type' => 'DDR4']);
        $this->install($asetB, ComponentCategory::Ram, ['capacity' => '8GB', 'type' => 'DDR4']);

        // Opsi RAM menampilkan total: 8GB dan 16GB.
        $this->assertSame(['8GB', '16GB'], Component::totalCapacityOptions('ram'));

        // Filter 16GB hanya menemukan aset dengan total 16GB (2x8GB).
        $this->actingAs($this->admin())
            ->get(route('assets.index', ['filter_category' => 'ram', 'filter_attribute' => 'total', 'component_value' => '16GB']))
            ->assertOk()
            ->assertSee('PC-16TOTAL')
            ->assertDontSee('PC-8ONLY');
    }

    public function test_ram_total_matches_single_module_of_same_size(): void
    {
        // 1x16GB harus setara dengan 2x8GB saat difilter 16GB.
        $single = Asset::factory()->create(['asset_code' => 'PC-1X16']);
        $double = Asset::factory()->create(['asset_code' => 'PC-2X8']);

        $this->install($single, ComponentCategory::Ram, ['capacity' => '16GB', 'type' => 'DDR4']);
        $this->install($double, ComponentCategory::Ram, ['capacity' => '8GB', 'type' => 'DDR4']);
        $this->install($double, ComponentCategory::Ram, ['capacity' => '8GB', 'type' => 'DDR4']);

        $this->actingAs($this->admin())
            ->get(route('assets.index', ['filter_category' => 'ram', 'filter_attribute' => 'total', 'component_value' => '16GB']))
            ->assertOk()
            ->assertSee('PC-1X16')
            ->assertSee('PC-2X8');
    }

    public function test_storage_filter_by_type(): void
    {
        $ssd = Asset::factory()->create(['asset_code' => 'PC-SSD']);
        $hdd = Asset::factory()->create(['asset_code' => 'PC-HDD']);
        $both = Asset::factory()->create(['asset_code' => 'PC-BOTH']);

        $this->install($ssd, ComponentCategory::Storage, ['capacity' => '512GB', 'type' => 'SSD']);
        $this->install($hdd, ComponentCategory::Storage, ['capacity' => '1TB', 'type' => 'HDD']);
        $this->install($both, ComponentCategory::Storage, ['capacity' => '512GB', 'type' => 'SSD']);
        $this->install($both, ComponentCategory::Storage, ['capacity' => '1TB', 'type' => 'HDD']);

        // Filter SSD: menemukan aset ber-SSD, termasuk yang punya SSD + HDD.
        $this->actingAs($this->admin())
            ->get(route('assets.index', ['filter_category' => 'storage', 'filter_attribute' => 'type', 'component_value' => 'SSD']))
            ->assertOk()
            ->assertSee('PC-SSD')
            ->assertSee('PC-BOTH')
            ->assertDontSee('PC-HDD');
    }

    public function test_filter_by_cpu_series(): void
    {
        $i7 = Asset::factory()->create(['asset_code' => 'PC-I7']);
        $i5 = Asset::factory()->create(['asset_code' => 'PC-I5']);

        $this->install($i7, ComponentCategory::Cpu, ['series' => 'i7-11700']);
        $this->install($i5, ComponentCategory::Cpu, ['series' => 'i5-10400']);

        $this->actingAs($this->admin())
            ->get(route('assets.index', ['filter_category' => 'cpu', 'filter_attribute' => 'series', 'component_value' => 'i7-11700']))
            ->assertOk()
            ->assertSee('PC-I7')
            ->assertDontSee('PC-I5');
    }

    public function test_filter_by_motherboard_chipset(): void
    {
        $h510 = Asset::factory()->create(['asset_code' => 'PC-H510']);
        $b560 = Asset::factory()->create(['asset_code' => 'PC-B560']);

        $this->install($h510, ComponentCategory::Motherboard, ['chipset' => 'H510']);
        $this->install($b560, ComponentCategory::Motherboard, ['chipset' => 'B560']);

        $this->actingAs($this->admin())
            ->get(route('assets.index', ['filter_category' => 'motherboard', 'filter_attribute' => 'chipset', 'component_value' => 'H510']))
            ->assertOk()
            ->assertSee('PC-H510')
            ->assertDontSee('PC-B560');
    }

    /**
     * Filter kategori tidak boleh "bocor": nilai harus berasal dari komponen
     * pada aset yang sama, bukan sembarang aset.
     */
    public function test_category_filter_does_not_leak_across_assets(): void
    {
        // Aset A punya storage SSD 1TB. Aset B punya storage HDD 512GB.
        $a = Asset::factory()->create(['asset_code' => 'PC-A-SSD']);
        $b = Asset::factory()->create(['asset_code' => 'PC-B-HDD']);

        $this->install($a, ComponentCategory::Storage, ['capacity' => '1TB', 'type' => 'SSD']);
        $this->install($b, ComponentCategory::Storage, ['capacity' => '512GB', 'type' => 'HDD']);

        // Filter SSD harus HANYA menemukan aset A, bukan B.
        $this->actingAs($this->admin())
            ->get(route('assets.index', ['filter_category' => 'storage', 'filter_attribute' => 'type', 'component_value' => 'SSD']))
            ->assertOk()
            ->assertSee('PC-A-SSD')
            ->assertDontSee('PC-B-HDD');
    }

    public function test_invalid_filter_value_shows_nothing(): void
    {
        Asset::factory()->create(['asset_code' => 'PC-ANY']);
        Component::factory()->ofCategory(ComponentCategory::Ram)->create(['specs' => ['capacity' => '8GB', 'type' => 'DDR4']]);

        $this->actingAs($this->admin())
            ->get(route('assets.index', ['filter_category' => 'ram', 'filter_attribute' => 'total', 'component_value' => '999GB']))
            ->assertOk()
            ->assertDontSee('PC-ANY');
    }

    public function test_filter_form_shows_category_and_value_dropdowns(): void
    {
        Component::factory()->ofCategory(ComponentCategory::Storage)->create(['specs' => ['capacity' => '512GB', 'type' => 'SSD']]);

        $this->actingAs($this->admin())
            ->get(route('assets.index'))
            ->assertOk()
            ->assertSee('Kategori Komponen')
            ->assertSee('Nilai')
            ->assertSee('Storage');
    }

    // ------------------------------------------------ R3 Kapasitas numerik

    public function test_capacity_is_parsed_to_mb_on_save(): void
    {
        $ram = Component::factory()->ofCategory(ComponentCategory::Ram)->create(['specs' => ['capacity' => '8GB']]);
        $disk = Component::factory()->ofCategory(ComponentCategory::Storage)->create(['specs' => ['capacity' => '1TB']]);

        $this->assertSame(8192, $ram->fresh()->capacity_mb);
        $this->assertSame(1048576, $disk->fresh()->capacity_mb);
    }

    public function test_capacity_parsing_handles_various_units(): void
    {
        $this->assertSame(512, Component::parseCapacityMb('512MB'));
        $this->assertSame(8192, Component::parseCapacityMb('8GB'));
        $this->assertSame(1048576, Component::parseCapacityMb('1TB'));
        $this->assertNull(Component::parseCapacityMb('tidak diketahui'));
        $this->assertNull(Component::parseCapacityMb(null));
    }

    public function test_capacity_formatting_round_trips(): void
    {
        $this->assertSame('8GB', Component::formatCapacityMb(8192));
        $this->assertSame('1TB', Component::formatCapacityMb(1048576));
        $this->assertSame('512MB', Component::formatCapacityMb(512));
    }
}
