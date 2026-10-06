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
            'specs' => ['cpu' => 'i5-10400', 'ram' => '16GB', 'storage' => '512GB SSD', 'os' => 'Windows 11'],
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
        $this->assertSame('i5-10400', $asset->specs['cpu']);
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

    // --------------------------------------------- Spesifikasi komponen

    public function test_all_spec_components_are_stored(): void
    {
        $specs = [
            'cpu' => 'Intel Core i7-11700',
            'ram' => '32GB DDR4',
            'storage' => '1TB NVMe SSD',
            'storage_2' => '2TB HDD',
            'gpu' => 'NVIDIA RTX 3060',
            'motherboard' => 'ASUS B560M',
            'psu' => '650W 80+ Gold',
            'casing' => 'ATX Mid Tower',
            'os' => 'Windows 11 Pro',
            'monitor' => 'Dell P2419H',
            'keyboard' => 'Logitech K120',
            'mouse' => 'Logitech B100',
        ];

        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['specs' => $specs]))
            ->assertSessionHasNoErrors();

        $stored = Asset::sole()->specs;

        foreach ($specs as $key => $value) {
            $this->assertSame($value, $stored[$key], "Spesifikasi [$key] tidak tersimpan.");
        }
    }

    public function test_empty_spec_components_are_not_stored(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'specs' => ['cpu' => 'i5', 'gpu' => '', 'psu' => '   '],
            ]))
            ->assertSessionHasNoErrors();

        $stored = Asset::sole()->specs;

        $this->assertArrayHasKey('cpu', $stored);
        $this->assertArrayNotHasKey('gpu', $stored);
        $this->assertArrayNotHasKey('psu', $stored);
    }

    public function test_unknown_spec_keys_are_ignored(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'specs' => ['cpu' => 'i5', 'sesuatu_yang_aneh' => 'x'],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('sesuatu_yang_aneh', Asset::sole()->specs);
    }

    public function test_asset_detail_displays_all_filled_specs_and_hostname(): void
    {
        $asset = Asset::factory()->create([
            'hostname' => 'pc-rnd-07',
            'specs' => ['cpu' => 'i7-11700', 'ram' => '32GB', 'gpu' => 'RTX 3060'],
        ]);

        $this->actingAs($this->admin())
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('pc-rnd-07')
            ->assertSee('i7-11700')
            ->assertSee('32GB')
            ->assertSee('RTX 3060')
            ->assertSee('GPU');
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

    public function test_cpu_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload(['specs' => ['ram' => '16GB']]))
            ->assertSessionHasErrors('specs.cpu');
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
                'specs' => ['cpu' => 'i7-11700', 'ram' => '32GB'],
                'status' => AssetStatus::Available->value,
            ])
            ->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $this->assertSame('Dell Updated', $asset->brand);
        $this->assertSame('10.0.0.5', $asset->ip_address);
        $this->assertSame('i7-11700', $asset->specs['cpu']);
    }

    public function test_type_and_asset_code_cannot_be_changed_via_update(): void
    {
        $asset = Asset::factory()->pc()->create();

        $this->actingAs($this->admin())
            ->put(route('assets.update', $asset), [
                'brand' => 'Dell',
                'mac_address' => $asset->mac_address,
                'ip_address' => null,
                'specs' => ['cpu' => 'i5'],
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
                'specs' => ['cpu' => 'i5'],
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
                'specs' => ['cpu' => 'i5'],
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
