<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @see dokumentasi/06-manajemen-aset.md
 * @see dokumentasi/10-validasi.md §5 dan §6
 */
class AssetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** Payload valid minimal untuk membuat aset. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => AssetType::PC->value,
            'brand' => 'Dell',
            'mac_address' => 'AA:BB:CC:DD:EE:01',
            'ip_address' => null,
            'specs' => ['os' => 'Windows 11'],
        ], $overrides);
    }

    // ---------------------------------------------------------------- Akses

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('assets.index'))->assertRedirect(route('login'));
    }

    public function test_viewer_can_view_asset_index_and_detail(): void
    {
        $asset = Asset::factory()->create();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('assets.index'))->assertOk();
        $this->actingAs($viewer)->get(route('assets.show', $asset))->assertOk();
    }

    public function test_viewer_cannot_access_write_routes(): void
    {
        $asset = Asset::factory()->create();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('assets.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('assets.store'), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get(route('assets.edit', $asset))->assertForbidden();
        $this->actingAs($viewer)->put(route('assets.update', $asset), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->delete(route('assets.destroy', $asset))->assertForbidden();
        $this->actingAs($viewer)->get(route('assets.trashed'))->assertForbidden();

        $this->assertDatabaseCount('assets', 1);
    }

    // ------------------------------------------------------------- Membuat

    public function test_admin_can_create_asset_with_generated_code(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $asset = Asset::sole();

        $this->assertSame('PC-'.now()->year.'-0001', $asset->asset_code);
        $this->assertSame(AssetStatus::Available, $asset->status);
        $this->assertNotNull($asset->created_by);
        $this->assertSame('Windows 11', $asset->specs['os']);
    }

    public function test_laptop_code_uses_lt_prefix(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['type' => AssetType::Laptop->value]));

        $this->assertSame('LT-'.now()->year.'-0001', Asset::sole()->asset_code);
    }

    public function test_generated_code_increments_per_type(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('assets.store'), $this->payload(['mac_address' => 'AA:BB:CC:DD:EE:01']));
        $this->actingAs($admin)->post(route('assets.store'), $this->payload(['mac_address' => 'AA:BB:CC:DD:EE:02']));
        $this->actingAs($admin)->post(route('assets.store'), $this->payload([
            'type' => AssetType::Laptop->value,
            'mac_address' => 'AA:BB:CC:DD:EE:03',
        ]));

        $codes = Asset::orderBy('id')->pluck('asset_code')->all();

        $this->assertSame([
            'PC-'.now()->year.'-0001',
            'PC-'.now()->year.'-0002',
            'LT-'.now()->year.'-0001',
        ], $codes);
    }

    /**
     * Kode aset dikunci: sistem selalu yang membuat, input manual diabaikan.
     */
    public function test_asset_code_from_form_is_ignored_and_generated_by_system(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['asset_code' => 'HACKED-0001']))
            ->assertSessionHasNoErrors();

        $asset = Asset::sole();

        $this->assertNotSame('HACKED-0001', $asset->asset_code);
        $this->assertSame('PC-'.now()->year.'-0001', $asset->asset_code);
    }

    public function test_store_form_does_not_expose_an_asset_code_input(): void
    {
        $this->actingAs($this->admin())
            ->get(route('assets.create'))
            ->assertOk()
            ->assertDontSee('name="asset_code"', false);
    }

    public function test_mac_address_is_normalized_to_uppercase(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['mac_address' => 'aa:bb:cc:dd:ee:0f']));

        $this->assertDatabaseHas('assets', ['mac_address' => 'AA:BB:CC:DD:EE:0F']);
    }

    // ------------------------------------------------------- Hostname

    public function test_hostname_is_saved_lowercase(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['hostname' => 'PC-RND-01']))
            ->assertSessionHasNoErrors();

        $this->assertSame('pc-rnd-01', Asset::sole()->hostname);
    }

    public function test_hostname_is_optional(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['hostname' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Asset::sole()->hostname);
    }

    public function test_duplicate_hostname_is_rejected(): void
    {
        Asset::factory()->create(['hostname' => 'pc-rnd-01']);

        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'hostname' => 'PC-RND-01',
                'mac_address' => 'AA:BB:CC:DD:EE:02',
            ]))
            ->assertSessionHasErrors('hostname');

        $this->assertDatabaseCount('assets', 1);
    }

    public function test_multiple_assets_may_have_no_hostname(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('assets.store'), $this->payload([
            'hostname' => null, 'mac_address' => 'AA:BB:CC:DD:EE:01',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('assets.store'), $this->payload([
            'hostname' => null, 'mac_address' => 'AA:BB:CC:DD:EE:02',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, Asset::whereNull('hostname')->count());
    }

    public function test_invalid_hostname_format_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['hostname' => 'PC RND 01']))
            ->assertSessionHasErrors('hostname');
    }

    public function test_hostname_can_be_reused_after_soft_delete(): void
    {
        Asset::factory()->create(['hostname' => 'pc-old-01'])->delete();

        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['hostname' => 'pc-old-01']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Asset::withTrashed()->where('hostname', 'pc-old-01')->count());
    }

    // ---------------------------------- Spesifikasi host (Fase 2: OS saja)

    public function test_only_os_is_stored_in_asset_specs(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['specs' => ['os' => 'Windows 11 Pro']]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['os' => 'Windows 11 Pro'], Asset::sole()->specs);
    }

    public function test_physical_part_keys_are_ignored_in_asset_specs(): void
    {
        // Part fisik kini komponen; key ini tidak lagi valid untuk host.
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'specs' => ['os' => 'Windows 11', 'cpu' => 'i7', 'ram' => '32GB', 'gpu' => 'RTX 3060'],
            ]))
            ->assertSessionHasNoErrors();

        $stored = Asset::sole()->specs;

        $this->assertSame('Windows 11', $stored['os']);
        $this->assertArrayNotHasKey('cpu', $stored);
        $this->assertArrayNotHasKey('ram', $stored);
        $this->assertArrayNotHasKey('gpu', $stored);
    }

    public function test_empty_os_is_not_stored(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['specs' => ['os' => '   ']]))
            ->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('os', Asset::sole()->specs);
    }

    public function test_hardware_summary_shows_technical_attributes_not_brand(): void
    {
        $asset = Asset::factory()->create();
        $component = \App\Models\Component::factory()
            ->ofCategory(\App\Enums\ComponentCategory::Ram)
            ->create([
                'brand' => 'Kingston',
                'model' => 'Fury Beast',
                'specs' => ['capacity' => '8GB', 'type' => 'DDR4'],
            ]);

        \App\Models\ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $asset->load('activeComponentInstallations.component');

        // X1: merek ikut tampil, dipisah spasi dari atribut teknis.
        $this->assertSame('Kingston 8GB DDR4', $asset->hardwareSummary());
    }

    public function test_hardware_summary_orders_cpu_then_ram_then_storage(): void
    {
        $asset = Asset::factory()->create();

        $make = function (\App\Enums\ComponentCategory $category, array $specs, array $extra = []) use ($asset) {
            $component = \App\Models\Component::factory()->ofCategory($category)->create($extra + ['specs' => $specs]);

            \App\Models\ComponentInstallation::factory()->create([
                'component_id' => $component->id,
                'asset_id' => $asset->id,
                'removed_date' => null,
            ]);
        };

        // Sengaja dibuat urutan terbalik untuk membuktikan pengurutan.
        $make(\App\Enums\ComponentCategory::Storage, ['capacity' => '512GB', 'type' => 'SSD'], ['brand' => 'Samsung']);
        $make(\App\Enums\ComponentCategory::Ram, ['capacity' => '16GB', 'type' => 'DDR4'], ['brand' => 'Kingston']);
        $make(\App\Enums\ComponentCategory::Cpu, ['series' => 'i7-11700'], ['brand' => 'Intel']);

        $asset->load('activeComponentInstallations.component');

        // X1: pemisah antar komponen ` | `, merek ikut tampil.
        $this->assertSame(
            'Intel i7-11700 | Kingston 16GB DDR4 | Samsung 512GB SSD',
            $asset->hardwareSummary()
        );
    }

    public function test_hardware_summary_is_empty_without_components(): void
    {
        $asset = Asset::factory()->create();

        $this->assertSame('—', $asset->hardwareSummary());
    }

    public function test_asset_detail_displays_os_hostname_and_components(): void
    {
        $asset = Asset::factory()->create([
            'hostname' => 'pc-rnd-07',
            'specs' => ['os' => 'Windows 11 Pro'],
        ]);

        $component = \App\Models\Component::factory()
            ->ofCategory(\App\Enums\ComponentCategory::Gpu)
            ->create(['brand' => 'NVIDIA', 'model' => 'RTX 3060', 'component_code' => 'GPU-2026-0001']);

        \App\Models\ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('pc-rnd-07')
            ->assertSee('Windows 11 Pro')
            ->assertSee('Komponen Terpasang')
            ->assertSee('GPU-2026-0001')
            ->assertSee('NVIDIA RTX 3060');
    }

    public function test_installed_component_blocks_asset_deletion(): void
    {
        $asset = Asset::factory()->create();
        $component = \App\Models\Component::factory()->create();

        \App\Models\ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('assets.destroy', $asset))
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('assets', ['id' => $asset->id]);
    }

    // ----------------------------------------------------------- Validasi

    public function test_duplicate_mac_is_rejected(): void
    {
        Asset::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:01']);

        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['mac_address' => 'AA:BB:CC:DD:EE:01']))
            ->assertSessionHasErrors('mac_address');

        $this->assertDatabaseCount('assets', 1);
    }

    public function test_duplicate_mac_is_rejected_case_insensitively(): void
    {
        Asset::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:01']);

        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['mac_address' => 'aa:bb:cc:dd:ee:01']))
            ->assertSessionHasErrors('mac_address');
    }

    public function test_duplicate_ip_is_rejected(): void
    {
        Asset::factory()->withIp('192.168.1.10')->create();

        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['ip_address' => '192.168.1.10']))
            ->assertSessionHasErrors('ip_address');

        $this->assertDatabaseCount('assets', 1);
    }

    public function test_multiple_assets_can_have_null_ip(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('assets.store'), $this->payload(['mac_address' => 'AA:BB:CC:DD:EE:01']));
        $this->actingAs($admin)->post(route('assets.store'), $this->payload(['mac_address' => 'AA:BB:CC:DD:EE:02']));
        $this->actingAs($admin)->post(route('assets.store'), $this->payload(['mac_address' => 'AA:BB:CC:DD:EE:03']));

        $this->assertSame(3, Asset::whereNull('ip_address')->count());
    }

    public function test_empty_string_ip_is_stored_as_null(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['ip_address' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Asset::sole()->ip_address);
    }

    /**
     * @dataProvider invalidMacProvider
     */
    public function test_invalid_mac_formats_are_rejected(string $mac): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['mac_address' => $mac]))
            ->assertSessionHasErrors('mac_address');
    }

    public static function invalidMacProvider(): array
    {
        return [
            'tanpa pemisah' => ['AABBCCDDEEFF'],
            'dipisah titik' => ['AABB.CCDD.EEFF'],
            'dipisah strip' => ['AA-BB-CC-DD-EE-FF'],
            'karakter non-hex' => ['GG:BB:CC:DD:EE:FF'],
            'terlalu pendek' => ['AA:BB:CC:DD:EE'],
            'all zero' => ['00:00:00:00:00:00'],
            'broadcast' => ['FF:FF:FF:FF:FF:FF'],
        ];
    }

    public function test_invalid_ip_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['ip_address' => '999.999.999.999']))
            ->assertSessionHasErrors('ip_address');
    }

    public function test_type_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['type' => 'Server']))
            ->assertSessionHasErrors('type');
    }

    public function test_asset_can_be_created_without_any_specs(): void
    {
        // Fase 2: tidak ada spesifikasi host yang wajib (OS opsional).
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['specs' => []]))
            ->assertSessionHasNoErrors();

        $this->assertSame([], Asset::sole()->specs);
    }

    // ------------------------------------------------------------- Mengubah

    public function test_admin_can_update_specs_and_ip(): void
    {
        $asset = Asset::factory()->create(['brand' => 'Dell', 'ip_address' => null]);

        $this->actingAs($this->admin())
            ->put(route('assets.update', $asset), [
                'brand' => 'Dell Updated',
                'mac_address' => $asset->mac_address,
                'ip_address' => '10.0.0.5',
                'specs' => ['os' => 'Windows 11 Pro'],
                'status' => AssetStatus::Available->value,
            ])
            ->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $this->assertSame('Dell Updated', $asset->brand);
        $this->assertSame('10.0.0.5', $asset->ip_address);
        $this->assertSame('Windows 11 Pro', $asset->specs['os']);
    }

    public function test_type_and_asset_code_cannot_be_changed_via_update(): void
    {
        $asset = Asset::factory()->pc()->create();

        $this->actingAs($this->admin())
            ->put(route('assets.update', $asset), [
                'brand' => 'Dell',
                'mac_address' => $asset->mac_address,
                'ip_address' => null,
                'specs' => ['os' => 'Windows 11'],
                'status' => AssetStatus::Available->value,
                'type' => AssetType::Laptop->value,
                'asset_code' => 'HACKED-0001',
            ]);

        $asset->refresh();
        $this->assertSame(AssetType::PC, $asset->type);
        $this->assertNotSame('HACKED-0001', $asset->asset_code);
    }

    public function test_update_ignores_own_mac_for_unique_check(): void
    {
        $asset = Asset::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:01']);

        $this->actingAs($this->admin())
            ->put(route('assets.update', $asset), [
                'brand' => 'Dell',
                'mac_address' => 'AA:BB:CC:DD:EE:01',
                'ip_address' => null,
                'specs' => ['os' => 'Windows 11'],
                'status' => AssetStatus::Available->value,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_status_cannot_be_manually_set_to_available_while_assigned(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Assigned]);
        Employee::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('assets.update', $asset), [
                'brand' => $asset->brand,
                'mac_address' => $asset->mac_address,
                'ip_address' => null,
                'specs' => ['os' => 'Windows 11'],
                'status' => AssetStatus::Available->value,
            ])
            ->assertSessionHasErrors('status');
    }

    // --------------------------------------------------------------- Hapus

    public function test_admin_can_soft_delete_available_asset(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('assets.destroy', $asset))
            ->assertRedirect(route('assets.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_asset_held_by_employee_cannot_be_deleted(): void
    {
        $asset = Asset::factory()->create();
        \App\Models\AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'returned_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('assets.destroy', $asset))
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHas('error', 'Aset sedang dipegang karyawan. Lakukan Return sebelum menghapus.');

        $this->assertNotSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_soft_deleted_asset_is_hidden_from_index(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'PC-2026-0001']);
        $asset->delete();

        $this->actingAs($this->admin())
            ->get(route('assets.index'))
            ->assertOk()
            ->assertDontSee('PC-2026-0001');
    }

    public function test_mac_of_deleted_asset_can_be_reused(): void
    {
        $asset = Asset::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:77']);
        $asset->delete();

        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['mac_address' => 'AA:BB:CC:DD:EE:77']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Asset::withTrashed()->where('mac_address', 'AA:BB:CC:DD:EE:77')->count());
    }

    // -------------------------------------------------------------- Restore

    public function test_admin_can_restore_asset(): void
    {
        $asset = Asset::factory()->create();
        $asset->delete();

        $this->actingAs($this->admin())
            ->post(route('assets.restore', $asset->id))
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_restore_is_blocked_when_mac_is_taken_by_another_active_asset(): void
    {
        $asset = Asset::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:88']);
        $asset->delete();

        Asset::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:88']);

        $this->actingAs($this->admin())
            ->post(route('assets.restore', $asset->id))
            ->assertRedirect(route('assets.trashed'))
            ->assertSessionHas('error');

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_restore_is_blocked_when_asset_code_is_taken_by_another_active_asset(): void
    {
        $asset = Asset::factory()->create([
            'asset_code' => 'PC-2026-0500',
            'mac_address' => 'AA:BB:CC:DD:EE:01',
        ]);
        $asset->delete();

        // Kode yang sama boleh dipakai aset aktif lain selama aset lama terhapus
        // (unique index bersifat parsial). Restore harus ditolak, bukan 500.
        Asset::factory()->create([
            'asset_code' => 'PC-2026-0500',
            'mac_address' => 'AA:BB:CC:DD:EE:02',
        ]);

        $this->actingAs($this->admin())
            ->post(route('assets.restore', $asset->id))
            ->assertRedirect(route('assets.trashed'))
            ->assertSessionHas('error', 'Aset tidak dapat dipulihkan: kode aset sudah dipakai aset aktif lain.');

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_trashed_page_lists_deleted_assets(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'PC-2026-0042']);
        $asset->delete();

        $this->actingAs($this->admin())
            ->get(route('assets.trashed'))
            ->assertOk()
            ->assertSee('PC-2026-0042');
    }

    public function test_force_delete_removes_asset_without_history(): void
    {
        $asset = Asset::factory()->create();
        $asset->delete();

        $this->actingAs($this->admin())
            ->delete(route('assets.force-delete', $asset->id))
            ->assertRedirect(route('assets.trashed'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
    }

    public function test_force_delete_is_blocked_when_asset_has_history(): void
    {
        $asset = Asset::factory()->create();
        \App\Models\AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'assigned_date' => now()->subDays(30)->format('Y-m-d'),
            'returned_date' => now()->subDays(2)->format('Y-m-d'),
        ]);
        $asset->delete();

        $this->actingAs($this->admin())
            ->delete(route('assets.force-delete', $asset->id))
            ->assertRedirect(route('assets.trashed'))
            ->assertSessionHas('error');

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }
}
