<?php

namespace Tests\Feature;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jenis aset CCTV & Printer (FB-4).
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-4
 */
class AssetTypeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => AssetType::PC->value,
            'brand' => 'Dell',
            'mac_address' => 'AA:BB:CC:DD:EE:01',
            'specs' => ['os' => 'Windows 11'],
        ], $overrides);
    }

    // --------------------------------------------------- Enum & prefix kode

    public function test_types_have_correct_classification(): void
    {
        $this->assertTrue(AssetType::PC->isComputer());
        $this->assertTrue(AssetType::Laptop->isComputer());
        $this->assertFalse(AssetType::Cctv->isComputer());
        $this->assertTrue(AssetType::Cctv->isDepartmentDevice());
        $this->assertTrue(AssetType::Printer->isDepartmentDevice());
    }

    public function test_mac_requirements_per_type(): void
    {
        $this->assertTrue(AssetType::PC->requiresMac());
        $this->assertTrue(AssetType::Laptop->requiresMac());
        $this->assertFalse(AssetType::Cctv->requiresMac());
        $this->assertFalse(AssetType::Printer->requiresMac());

        // Printer: MAC tidak dipakai sama sekali.
        $this->assertFalse(AssetType::Printer->allowsMac());
        $this->assertTrue(AssetType::Cctv->allowsMac());
    }

    public function test_component_support_only_for_computers(): void
    {
        $this->assertTrue(AssetType::PC->supportsComponents());
        $this->assertFalse(AssetType::Cctv->supportsComponents());
        $this->assertFalse(AssetType::Printer->supportsComponents());
    }

    public function test_code_prefix_per_type(): void
    {
        $this->assertSame('PC', AssetType::PC->codePrefix());
        $this->assertSame('LT', AssetType::Laptop->codePrefix());
        $this->assertSame('CCTV', AssetType::Cctv->codePrefix());
        $this->assertSame('PRN', AssetType::Printer->codePrefix());
    }

    // ------------------------------------------------------------- Membuat

    public function test_admin_can_create_cctv_with_department(): void
    {
        $department = Department::create(['nama_dept' => 'EDP']);

        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'type' => AssetType::Cctv->value,
                'brand' => 'Hikvision DS-2CD',
                'mac_address' => null,
                'department_id' => $department->id,
                'specs' => [],
            ]))
            ->assertSessionHasNoErrors();

        $asset = Asset::sole();

        $this->assertSame(AssetType::Cctv, $asset->type);
        $this->assertSame('CCTV-'.now()->year.'-0001', $asset->asset_code);
        $this->assertSame($department->id, $asset->department_id);
        $this->assertNull($asset->mac_address);
    }

    public function test_cctv_can_still_have_mac(): void
    {
        $department = Department::create(['nama_dept' => 'EDP']);

        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'type' => AssetType::Cctv->value,
                'mac_address' => 'AA:BB:CC:DD:EE:99',
                'department_id' => $department->id,
                'specs' => [],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('AA:BB:CC:DD:EE:99', Asset::sole()->mac_address);
    }

    public function test_admin_can_create_printer_without_mac(): void
    {
        $department = Department::create(['nama_dept' => 'HRGA']);

        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'type' => AssetType::Printer->value,
                'brand' => 'Epson L3210',
                'mac_address' => null,
                'department_id' => $department->id,
                'specs' => [],
            ]))
            ->assertSessionHasNoErrors();

        $asset = Asset::sole();

        $this->assertSame(AssetType::Printer, $asset->type);
        $this->assertSame('PRN-'.now()->year.'-0001', $asset->asset_code);
        $this->assertNull($asset->mac_address);
    }

    public function test_printer_requires_department(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'type' => AssetType::Printer->value,
                'mac_address' => null,
                'department_id' => null,
                'specs' => [],
            ]))
            ->assertSessionHasErrors('department_id');
    }

    public function test_cctv_does_not_require_department(): void
    {
        // CCTV tanggung jawab Admin IT, tanpa pemilik.
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'type' => AssetType::Cctv->value,
                'mac_address' => null,
                'department_id' => null,
                'specs' => [],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Asset::sole()->department_id);
    }

    public function test_only_computers_are_assignable(): void
    {
        $this->assertTrue(AssetType::PC->isAssignable());
        $this->assertTrue(AssetType::Laptop->isAssignable());
        $this->assertFalse(AssetType::Cctv->isAssignable());
        $this->assertFalse(AssetType::Printer->isAssignable());
    }

    public function test_only_printer_requires_department(): void
    {
        $this->assertTrue(AssetType::Printer->requiresDepartment());
        $this->assertFalse(AssetType::Cctv->requiresDepartment());
        $this->assertFalse(AssetType::PC->requiresDepartment());
        $this->assertFalse(AssetType::Laptop->requiresDepartment());
    }

    public function test_cctv_cannot_be_assigned_to_employee(): void
    {
        $asset = Asset::factory()->create([
            'type' => AssetType::Cctv,
            'mac_address' => null,
        ]);
        $employee = \App\Models\Employee::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('assets.assign.store', $asset), [
                'employee_id' => $employee->id,
                'assigned_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('asset_id');

        $this->assertDatabaseCount('asset_assignments', 0);
    }

    public function test_computer_still_requires_mac(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'type' => AssetType::PC->value,
                'mac_address' => null,
            ]))
            ->assertSessionHasErrors('mac_address');
    }

    public function test_computer_does_not_require_department(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'type' => AssetType::PC->value,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Asset::sole()->department_id);
    }

    // ----------------------------------------------------------- Tampilan

    public function test_index_shows_department_device_owner(): void
    {
        $department = Department::create(['nama_dept' => 'EDP']);
        Asset::factory()->create([
            'type' => AssetType::Cctv,
            'hostname' => 'cctv-edp-01',
            'mac_address' => null,
            'department_id' => $department->id,
        ]);

        $this->actingAs($this->admin())
            ->get(route('assets.index'))
            ->assertOk()
            ->assertSee('Perangkat departemen');
    }

    public function test_create_form_offers_all_four_types(): void
    {
        Department::create(['nama_dept' => 'EDP']);

        $this->actingAs($this->admin())
            ->get(route('assets.create'))
            ->assertOk()
            ->assertSee('value="CCTV"', false)
            ->assertSee('value="Printer"', false)
            ->assertSee('Departemen Pemilik');
    }

    public function test_duplicate_hostname_with_existing_computer_is_rejected(): void
    {
        Asset::factory()->create(['hostname' => 'cctv-edp-01']);
        $department = Department::create(['nama_dept' => 'EDP']);

        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'type' => AssetType::Cctv->value,
                'hostname' => 'cctv-edp-01',
                'mac_address' => null,
                'department_id' => $department->id,
                'specs' => [],
            ]))
            ->assertSessionHasErrors('hostname');
    }

    // -------------------------------------------- U3b: merek per jenis

    public function test_brand_is_required_for_laptop_cctv_printer(): void
    {
        $this->assertFalse(AssetType::PC->requiresBrand());
        $this->assertTrue(AssetType::Laptop->requiresBrand());
        $this->assertTrue(AssetType::Cctv->requiresBrand());
        $this->assertTrue(AssetType::Printer->requiresBrand());
    }

    public function test_pc_without_brand_is_accepted(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'type' => AssetType::PC->value,
                'brand' => null,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Asset::sole()->brand);
    }

    public function test_laptop_without_brand_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'type' => AssetType::Laptop->value,
                'brand' => null,
            ]))
            ->assertSessionHasErrors('brand');
    }

    public function test_cctv_without_brand_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'type' => AssetType::Cctv->value,
                'brand' => null,
                'mac_address' => null,
                'specs' => [],
            ]))
            ->assertSessionHasErrors('brand');
    }

    public function test_printer_without_brand_is_rejected(): void
    {
        $department = Department::create(['nama_dept' => 'HRGA']);

        $this->actingAs($this->admin())
            ->post(route('assets.store'), $this->payload([
                'type' => AssetType::Printer->value,
                'brand' => null,
                'mac_address' => null,
                'department_id' => $department->id,
                'specs' => [],
            ]))
            ->assertSessionHasErrors('brand');
    }

    // --------------------------------- U3a: tampilan CCTV/Printer

    public function test_cctv_detail_hides_computer_only_sections(): void
    {
        $asset = Asset::factory()->cctv()->create(['hostname' => 'cctv-edp-01']);

        $html = $this->actingAs($this->admin())
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->getContent();

        // Bagian khusus komputer tidak boleh muncul.
        $this->assertStringNotContainsString('Pemegang Saat Ini', $html);
        $this->assertStringNotContainsString('Komponen Terpasang', $html);
        $this->assertStringNotContainsString('Riwayat Pemakaian', $html);
        $this->assertStringNotContainsString('Akses Remote & Kredensial', $html);
        $this->assertStringNotContainsString('Sistem Operasi', $html);
        // Informasi aset dasar tetap ada.
        $this->assertStringContainsString('Informasi Aset', $html);
        $this->assertStringContainsString('MAC Address', $html);
    }

    public function test_printer_detail_shows_owner_department(): void
    {
        $department = Department::create(['nama_dept' => 'HRGA']);
        $asset = Asset::factory()->printer()->create(['department_id' => $department->id]);

        $this->actingAs($this->admin())
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Departemen Pemilik')
            ->assertSee('HRGA')
            ->assertDontSee('Pemegang Saat Ini');
    }

    public function test_computer_detail_still_shows_all_sections(): void
    {
        $asset = Asset::factory()->pc()->create();

        $this->actingAs($this->admin())
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Pemegang Saat Ini')
            ->assertSee('Komponen Terpasang')
            ->assertSee('Riwayat Pemakaian');
    }

    // --------------------------------- U3c: form aset per jenis

    public function test_create_form_hides_os_for_department_devices(): void
    {
        Department::create(['nama_dept' => 'EDP']);

        $html = $this->actingAs($this->admin())
            ->get(route('assets.create'))
            ->assertOk()
            ->getContent();

        // Kartu OS memakai x-show yang hanya tampil untuk PC/Laptop.
        $this->assertStringContainsString("x-show=\"type === 'PC' || type === 'Laptop'\"", $html);
    }

    public function test_edit_form_hides_os_for_department_devices(): void
    {
        $cctv = Asset::factory()->cctv()->create(['hostname' => 'cctv-edp-01']);
        $printer = Asset::factory()->printer()->create();

        foreach ([$cctv, $printer] as $asset) {
            $this->actingAs($this->admin())
                ->get(route('assets.edit', $asset))
                ->assertOk()
                ->assertDontSee('Sistem Operasi')
                ->assertDontSee('specs[os]', false);
        }
    }

    public function test_edit_form_still_shows_os_for_computers(): void
    {
        $pc = Asset::factory()->pc()->create();

        $this->actingAs($this->admin())
            ->get(route('assets.edit', $pc))
            ->assertOk()
            ->assertSee('Sistem Operasi')
            ->assertSee('specs[os]', false);
    }

    /**
     * Karena form CCTV/Printer tidak lagi merender kartu OS, update tidak boleh
     * menghapus `specs` yang mungkin sudah terlanjur tersimpan.
     */
    public function test_updating_department_device_keeps_existing_specs(): void
    {
        $cctv = Asset::factory()->cctv()->create([
            'hostname' => 'cctv-edp-01',
            'specs' => ['os' => 'Linux'],
        ]);

        $this->actingAs($this->admin())
            ->put(route('assets.update', $cctv), [
                'brand' => 'Hikvision',
                'mac_address' => 'AA:BB:CC:DD:EE:09',
                'ip_address' => '192.168.10.99',
                'status' => 'Available',
            ])
            ->assertRedirect(route('assets.show', $cctv));

        $this->assertSame(['os' => 'Linux'], $cctv->fresh()->specs);
    }

    public function test_edit_form_mac_field_matches_type(): void
    {
        $printer = Asset::factory()->printer()->create();
        $cctv = Asset::factory()->cctv()->create(['hostname' => 'cctv-edp-01']);
        $pc = Asset::factory()->pc()->create();

        // Printer: MAC tidak dipakai sama sekali → field tidak dirender.
        $this->actingAs($this->admin())
            ->get(route('assets.edit', $printer))
            ->assertOk()
            ->assertDontSee('MAC Address');

        // CCTV: MAC opsional → field ada, tapi `required` tidak aktif.
        $cctvHtml = $this->actingAs($this->admin())
            ->get(route('assets.edit', $cctv))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('MAC Address', $cctvHtml);
        $this->assertStringContainsString('x-bind:required="false"', $cctvHtml);

        // PC: MAC wajib → `required` aktif.
        $pcHtml = $this->actingAs($this->admin())
            ->get(route('assets.edit', $pc))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('x-bind:required="true"', $pcHtml);
    }

    // ---------------------------------------- X2: lokasi khusus CCTV

    public function test_only_cctv_supports_location(): void
    {
        $this->assertTrue(AssetType::Cctv->supportsLocation());
        $this->assertFalse(AssetType::PC->supportsLocation());
        $this->assertFalse(AssetType::Laptop->supportsLocation());
        $this->assertFalse(AssetType::Printer->supportsLocation());
    }

    public function test_cctv_can_be_created_with_location(): void
    {
        $this->actingAs($this->admin())->post(route('assets.store'), [
            'type' => AssetType::Cctv->value,
            'brand' => 'Hikvision',
            'location' => 'Lobby Utama',
            'specs' => [],
        ])->assertRedirect();

        $this->assertSame('Lobby Utama', Asset::where('type', 'CCTV')->sole()->location);
    }

    public function test_location_is_ignored_for_non_cctv(): void
    {
        $this->actingAs($this->admin())->post(route('assets.store'), [
            'type' => AssetType::PC->value,
            'brand' => 'Dell',
            'mac_address' => 'AA:BB:CC:DD:EE:21',
            'location' => 'Lobby Utama',
            'specs' => [],
        ])->assertRedirect();

        $this->assertNull(Asset::where('type', 'PC')->sole()->location);
    }

    public function test_cctv_detail_shows_location(): void
    {
        $cctv = Asset::factory()->cctv()->create([
            'hostname' => 'cctv-edp-01',
            'location' => 'Lobby Utama',
        ]);

        $this->actingAs($this->admin())
            ->get(route('assets.show', $cctv))
            ->assertOk()
            ->assertSee('Lokasi')
            ->assertSee('Lobby Utama');
    }

    public function test_computer_detail_has_no_location(): void
    {
        $pc = Asset::factory()->pc()->create(['location' => 'Lobby Utama']);

        $this->actingAs($this->admin())
            ->get(route('assets.show', $pc))
            ->assertOk()
            ->assertDontSee('Lokasi');
    }
}
