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
}
