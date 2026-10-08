<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\ComponentCategory;
use App\Enums\ComponentStatus;
use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentInstallation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD komponen (part) komputer.
 *
 * @see dokumentasi/14-manajemen-komponen.md
 */
class ComponentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category' => ComponentCategory::Ram->value,
            'brand' => 'Kingston',
            'model' => 'Fury Beast DDR4',
            'serial_number' => 'SN-RAM-TEST-01',
            'specs' => ['capacity' => '8GB', 'type' => 'DDR4', 'speed' => '3200MHz'],
            'notes' => null,
        ], $overrides);
    }

    // ------------------------------------------------------------- Akses

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('components.index'))->assertRedirect(route('login'));
    }

    public function test_viewer_can_view_component_list_and_detail(): void
    {
        $component = Component::factory()->create();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('components.index'))->assertOk();
        $this->actingAs($viewer)->get(route('components.show', $component))->assertOk();
    }

    public function test_viewer_cannot_access_write_routes(): void
    {
        $component = Component::factory()->create();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('components.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('components.store'), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get(route('components.edit', $component))->assertForbidden();
        $this->actingAs($viewer)->put(route('components.update', $component), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->delete(route('components.destroy', $component))->assertForbidden();
        $this->actingAs($viewer)->get(route('components.trashed'))->assertForbidden();

        $this->assertDatabaseCount('components', 1);
    }

    // ------------------------------------------------------ AC-5: Buat

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->admin())
            ->get(route('components.create'))
            ->assertOk()
            ->assertSee('Tambah Komponen')
            ->assertDontSee('name="component_code"', false);
    }

    public function test_edit_page_renders(): void
    {
        $component = Component::factory()->ofCategory(ComponentCategory::Ram)->create();

        $this->actingAs($this->admin())
            ->get(route('components.edit', $component))
            ->assertOk()
            ->assertSee($component->component_code)
            ->assertSee('Edit Komponen');
    }

    public function test_admin_can_create_component_with_generated_code(): void
    {
        $this->actingAs($this->admin())
            ->post(route('components.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $component = Component::sole();

        $this->assertSame('RAM-'.now()->year.'-0001', $component->component_code);
        $this->assertSame(ComponentStatus::InStock, $component->status);
        $this->assertSame(ComponentCategory::Ram, $component->category);
        $this->assertNotNull($component->created_by);
    }

    public function test_code_prefix_follows_category(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('components.store'), $this->payload([
            'category' => ComponentCategory::Storage->value,
            'serial_number' => 'SN-DSK-1',
            'specs' => ['capacity' => '512GB'],
        ]));

        $this->actingAs($admin)->post(route('components.store'), $this->payload([
            'category' => ComponentCategory::Monitor->value,
            'serial_number' => 'SN-MON-1',
            'specs' => ['size' => '24"'],
        ]));

        $this->assertSame(
            ['DSK-'.now()->year.'-0001', 'MON-'.now()->year.'-0001'],
            Component::orderBy('id')->pluck('component_code')->all()
        );
    }

    public function test_serial_number_is_optional(): void
    {
        $this->actingAs($this->admin())
            ->post(route('components.store'), $this->payload(['serial_number' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Component::sole()->serial_number);
    }

    public function test_empty_serial_can_repeat(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('components.store'), $this->payload(['serial_number' => null]))
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('components.store'), $this->payload([
            'serial_number' => null, 'brand' => 'Corsair',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, Component::whereNull('serial_number')->count());
    }

    public function test_duplicate_serial_is_rejected(): void
    {
        Component::factory()->create(['serial_number' => 'SN-DUP-1']);

        $this->actingAs($this->admin())
            ->post(route('components.store'), $this->payload(['serial_number' => 'SN-DUP-1']))
            ->assertSessionHasErrors('serial_number');

        $this->assertDatabaseCount('components', 1);
    }

    public function test_status_is_forced_to_in_stock_on_create(): void
    {
        $this->actingAs($this->admin())
            ->post(route('components.store'), $this->payload(['status' => ComponentStatus::Retired->value]));

        $this->assertSame(ComponentStatus::InStock, Component::sole()->status);
    }

    // ---------------------------------------------------------- Validasi

    public function test_category_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('components.store'), $this->payload(['category' => 'nonsense']))
            ->assertSessionHasErrors('category');
    }

    public function test_brand_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('components.store'), $this->payload(['brand' => '']))
            ->assertSessionHasErrors('brand');
    }

    public function test_spec_keys_are_scoped_to_selected_category(): void
    {
        // 'socket' bukan key RAM; tidak boleh tersimpan.
        $this->actingAs($this->admin())
            ->post(route('components.store'), $this->payload([
                'specs' => ['capacity' => '8GB', 'socket' => 'LGA1200'],
            ]))
            ->assertSessionHasNoErrors();

        $specs = Component::sole()->specs;

        $this->assertSame('8GB', $specs['capacity']);
        $this->assertArrayNotHasKey('socket', $specs);
    }

    public function test_empty_spec_values_are_not_stored(): void
    {
        $this->actingAs($this->admin())
            ->post(route('components.store'), $this->payload([
                'specs' => ['capacity' => '8GB', 'speed' => '  '],
            ]))
            ->assertSessionHasNoErrors();

        $specs = Component::sole()->specs;

        $this->assertArrayHasKey('capacity', $specs);
        $this->assertArrayNotHasKey('speed', $specs);
    }

    // --------------------------------------------------------- Mengubah

    public function test_admin_can_update_component(): void
    {
        $component = Component::factory()->ofCategory(ComponentCategory::Ram)->create();

        $this->actingAs($this->admin())
            ->put(route('components.update', $component), [
                'brand' => 'Corsair',
                'model' => 'Vengeance',
                'serial_number' => $component->serial_number,
                'specs' => ['capacity' => '16GB', 'type' => 'DDR4'],
                'status' => ComponentStatus::InRepair->value,
            ])
            ->assertRedirect(route('components.show', $component));

        $component->refresh();
        $this->assertSame('Corsair', $component->brand);
        $this->assertSame('16GB', $component->specs['capacity']);
        $this->assertSame(ComponentStatus::InRepair, $component->status);
    }

    public function test_category_cannot_be_changed_via_update(): void
    {
        $component = Component::factory()->ofCategory(ComponentCategory::Ram)->create();

        $this->actingAs($this->admin())
            ->put(route('components.update', $component), [
                'brand' => $component->brand,
                'serial_number' => $component->serial_number,
                'specs' => ['capacity' => '8GB'],
                'status' => ComponentStatus::InStock->value,
                'category' => ComponentCategory::Gpu->value,
            ]);

        $this->assertSame(ComponentCategory::Ram, $component->fresh()->category);
    }

    public function test_status_cannot_be_manually_set_to_in_stock_while_installed(): void
    {
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);
        $asset = Asset::factory()->create();

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->put(route('components.update', $component), [
                'brand' => $component->brand,
                'serial_number' => $component->serial_number,
                'specs' => [],
                'status' => ComponentStatus::InStock->value,
            ])
            ->assertSessionHasErrors('status');
    }

    // --------------------------------------------------------------- Hapus

    public function test_admin_can_soft_delete_in_stock_component(): void
    {
        $component = Component::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('components.destroy', $component))
            ->assertRedirect(route('components.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('components', ['id' => $component->id]);
    }

    public function test_installed_component_cannot_be_deleted(): void
    {
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);
        $asset = Asset::factory()->create(['status' => AssetStatus::Assigned]);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('components.destroy', $component))
            ->assertRedirect(route('components.show', $component))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('components', ['id' => $component->id]);
    }

    public function test_serial_can_be_reused_after_soft_delete(): void
    {
        Component::factory()->create(['serial_number' => 'SN-REUSE-1'])->delete();

        $this->actingAs($this->admin())
            ->post(route('components.store'), $this->payload(['serial_number' => 'SN-REUSE-1']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Component::withTrashed()->where('serial_number', 'SN-REUSE-1')->count());
    }

    public function test_admin_can_restore_component(): void
    {
        $component = Component::factory()->create();
        $component->delete();

        $this->actingAs($this->admin())
            ->post(route('components.restore', $component->id))
            ->assertRedirect(route('components.show', $component))
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted('components', ['id' => $component->id]);
    }

    public function test_restore_is_blocked_when_code_is_taken(): void
    {
        $component = Component::factory()->create(['component_code' => 'RAM-2026-0500']);
        $component->delete();

        Component::factory()->create(['component_code' => 'RAM-2026-0500']);

        $this->actingAs($this->admin())
            ->post(route('components.restore', $component->id))
            ->assertRedirect(route('components.trashed'))
            ->assertSessionHas('error');

        $this->assertSoftDeleted('components', ['id' => $component->id]);
    }

    // ------------------------------------------------------- Daftar & cari

    public function test_index_shows_location_of_component(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'PC-RND-09']);
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->get(route('components.index'))
            ->assertOk()
            ->assertSee('PC-RND-09');
    }

    public function test_index_shows_warehouse_for_uninstalled_component(): void
    {
        Component::factory()->create(['status' => ComponentStatus::InStock]);

        $this->actingAs($this->admin())
            ->get(route('components.index'))
            ->assertOk()
            ->assertSee('Gudang');
    }

    public function test_index_can_search_by_serial_and_filter_by_category(): void
    {
        Component::factory()->create([
            'serial_number' => 'TARGET-SN-1', 'brand' => 'Kingston',
        ]);
        Component::factory()->ofCategory(ComponentCategory::Monitor)->create([
            'serial_number' => 'OTHER-SN-2',
        ]);

        $this->actingAs($this->admin())
            ->get(route('components.index', ['q' => 'TARGET-SN-1']))
            ->assertOk()
            ->assertSee('TARGET-SN-1')
            ->assertDontSee('OTHER-SN-2');
    }

    public function test_show_displays_installation_history(): void
    {
        $component = Component::factory()->create(['component_code' => 'RAM-2026-0007']);
        $old = Asset::factory()->create(['asset_code' => 'PC-OLD-01']);
        $current = Asset::factory()->create(['asset_code' => 'PC-NEW-02']);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $old->id,
            'installed_date' => now()->subDays(90)->format('Y-m-d'),
            'removed_date' => now()->subDays(30)->format('Y-m-d'),
        ]);
        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $current->id,
            'installed_date' => now()->subDays(30)->format('Y-m-d'),
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->get(route('components.show', $component))
            ->assertOk()
            ->assertSee('Riwayat Pemasangan')
            ->assertSee('PC-OLD-01')
            ->assertSee('PC-NEW-02')
            ->assertSee('Lokasi Saat Ini');
    }

    // ------------------------------ Regresi: duplikat nama input (W1)

    /**
     * RAM & Storage sama-sama memakai key `specs[capacity]` dan `specs[type]`.
     * Karena semua kategori dirender di DOM (disembunyikan dengan x-show),
     * browser akan mengirim DUA nilai untuk nama yang sama dan PHP mengambil
     * yang TERAKHIR — yaitu milik kategori tersembunyi — sehingga spesifikasi
     * kategori aktif hilang.
     *
     * Perbaikannya: tiap kategori dibungkus <fieldset> yang di-DISABLE saat
     * tidak aktif (kontrol disabled tidak ikut ter-submit).
     */
    public function test_create_form_disables_inactive_category_fields(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('components.create'))
            ->assertOk()
            ->getContent();

        // Ada fieldset dengan binding disabled per kategori.
        $this->assertStringContainsString('x-bind:disabled="category !== ', $html);

        // Setiap kategori punya fieldset-nya sendiri.
        $this->assertGreaterThanOrEqual(
            count(ComponentCategory::cases()),
            substr_count($html, '<fieldset'),
            'Setiap kategori harus dibungkus fieldset agar tidak saling menimpa.'
        );
    }

    public function test_ram_and_storage_share_spec_keys(): void
    {
        // Fakta yang menyebabkan bug: key `capacity`/`type` dipakai dua kategori.
        $this->assertContains('capacity', ComponentCategory::Ram->specKeys());
        $this->assertContains('capacity', ComponentCategory::Storage->specKeys());
        $this->assertContains('type', ComponentCategory::Ram->specKeys());
        $this->assertContains('type', ComponentCategory::Storage->specKeys());
    }

    /**
     * Ringkasan menggabungkan komponen ber-spesifikasi SAMA **dan merek sama**.
     *
     * X1: merek kini ikut tampil, sehingga dua keping dengan merek berbeda
     * tidak lagi digabung menjadi satu entri.
     */
    public function test_summary_merges_same_specs_and_brand(): void
    {
        $asset = Asset::factory()->create();

        foreach (['Kingston', 'Kingston'] as $brand) {
            $ram = Component::factory()->ofCategory(ComponentCategory::Ram)->create([
                'brand' => $brand,
                'specs' => ['capacity' => '16GB', 'type' => 'DDR4'],
            ]);

            \App\Models\ComponentInstallation::factory()->create([
                'component_id' => $ram->id,
                'asset_id' => $asset->id,
                'removed_date' => null,
            ]);
        }

        $asset->load('activeComponentInstallations.component');

        // Merek sama → digabung, dengan merek di depan.
        $this->assertSame('Kingston 16GB DDR4 x2', $asset->hardwareSummary());
        $this->assertStringContainsString('RAM: Kingston 16GB DDR4 x2', $asset->hardwareSummaryDetailed());
    }

    /**
     * Merek berbeda → tidak digabung, karena merek kini bagian dari ringkasan (X1).
     */
    public function test_summary_keeps_different_brands_separate(): void
    {
        $asset = Asset::factory()->create();

        foreach (['Kingston', 'Samsung'] as $brand) {
            $ram = Component::factory()->ofCategory(ComponentCategory::Ram)->create([
                'brand' => $brand,
                'specs' => ['capacity' => '16GB', 'type' => 'DDR4'],
            ]);

            \App\Models\ComponentInstallation::factory()->create([
                'component_id' => $ram->id,
                'asset_id' => $asset->id,
                'removed_date' => null,
            ]);
        }

        $asset->load('activeComponentInstallations.component');

        $this->assertSame('Kingston 16GB DDR4 + Samsung 16GB DDR4', $asset->hardwareSummary());
    }
}
