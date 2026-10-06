<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pencarian dan filter daftar aset.
 *
 * @see dokumentasi/06-manajemen-aset.md §6
 */
class AssetSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** Buat aset yang dipegang karyawan pada departemen tertentu. */
    private function assignedAsset(array $assetAttributes, Employee $employee): Asset
    {
        $asset = Asset::factory()->create(array_merge(
            ['status' => AssetStatus::Assigned],
            $assetAttributes
        ));

        AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'employee_id' => $employee->id,
            'returned_date' => null,
        ]);

        return $asset;
    }

    public function test_search_by_mac_address(): void
    {
        Asset::factory()->create(['asset_code' => 'PC-A', 'mac_address' => 'AA:BB:CC:DD:EE:01']);
        Asset::factory()->create(['asset_code' => 'PC-B', 'mac_address' => '11:22:33:44:55:66']);

        $this->actingAs($this->admin())
            ->get(route('assets.index', ['q' => 'AA:BB:CC:DD:EE:01']))
            ->assertOk()
            ->assertSee('PC-A')
            ->assertDontSee('PC-B');
    }

    public function test_search_by_hostname(): void
    {
        Asset::factory()->create(['asset_code' => 'PC-A', 'hostname' => 'pc-rnd-01']);
        Asset::factory()->create(['asset_code' => 'PC-B', 'hostname' => 'pc-edp-99']);

        $this->actingAs($this->admin())
            ->get(route('assets.index', ['q' => 'pc-rnd-01']))
            ->assertOk()
            ->assertSee('PC-A')
            ->assertDontSee('PC-B');
    }

    public function test_search_by_ip_address(): void
    {
        Asset::factory()->create(['asset_code' => 'PC-A', 'ip_address' => '10.0.0.1']);
        Asset::factory()->create(['asset_code' => 'PC-B', 'ip_address' => '10.0.0.2']);

        $this->actingAs($this->admin())
            ->get(route('assets.index', ['q' => '10.0.0.2']))
            ->assertOk()
            ->assertSee('PC-B')
            ->assertDontSee('PC-A');
    }

    public function test_search_by_holder_name(): void
    {
        $rnd = Department::create(['nama_dept' => 'RnD']);
        $budi = Employee::factory()->forDepartment($rnd)->create(['nama' => 'Budi']);
        $siti = Employee::factory()->forDepartment($rnd)->create(['nama' => 'Siti']);

        // Kode aset sengaja TIDAK memuat nama, agar hanya jalur pencarian
        // nama pemegang (activeAssignment.employee.nama) yang bisa cocok.
        $this->assignedAsset(['asset_code' => 'PC-0001'], $budi);
        $this->assignedAsset(['asset_code' => 'PC-0002'], $siti);

        $this->actingAs($this->admin())
            ->get(route('assets.index', ['q' => 'Budi']))
            ->assertOk()
            ->assertSee('PC-0001')
            ->assertDontSee('PC-0002');
    }

    public function test_search_is_case_insensitive(): void
    {
        Asset::factory()->create(['asset_code' => 'PC-A', 'brand' => 'Lenovo']);
        Asset::factory()->create(['asset_code' => 'PC-B', 'brand' => 'Dell']);

        $this->actingAs($this->admin())
            ->get(route('assets.index', ['q' => 'lenovo']))
            ->assertOk()
            ->assertSee('PC-A')
            ->assertDontSee('PC-B');
    }

    public function test_filter_by_status(): void
    {
        Asset::factory()->create(['asset_code' => 'PC-AVAIL', 'status' => AssetStatus::Available]);
        Asset::factory()->create(['asset_code' => 'PC-REPAIR', 'status' => AssetStatus::InRepair]);

        $this->actingAs($this->admin())
            ->get(route('assets.index', ['status' => AssetStatus::InRepair->value]))
            ->assertOk()
            ->assertSee('PC-REPAIR')
            ->assertDontSee('PC-AVAIL');
    }

    public function test_filter_by_type(): void
    {
        Asset::factory()->pc()->create(['asset_code' => 'PC-001']);
        Asset::factory()->laptop()->create(['asset_code' => 'LT-001']);

        $this->actingAs($this->admin())
            ->get(route('assets.index', ['type' => AssetType::Laptop->value]))
            ->assertOk()
            ->assertSee('LT-001')
            ->assertDontSee('PC-001');
    }

    public function test_filter_by_department_of_current_holder(): void
    {
        $rnd = Department::create(['nama_dept' => 'RnD']);
        $edp = Department::create(['nama_dept' => 'EDP']);

        $this->assignedAsset(['asset_code' => 'PC-RND'], Employee::factory()->forDepartment($rnd)->create());
        $this->assignedAsset(['asset_code' => 'PC-EDP'], Employee::factory()->forDepartment($edp)->create());

        $this->actingAs($this->admin())
            ->get(route('assets.index', ['department_id' => $rnd->id]))
            ->assertOk()
            ->assertSee('PC-RND')
            ->assertDontSee('PC-EDP');
    }

    public function test_search_combines_with_status_and_department_filters(): void
    {
        $rnd = Department::create(['nama_dept' => 'RnD']);
        $edp = Department::create(['nama_dept' => 'EDP']);

        $budi = Employee::factory()->forDepartment($rnd)->create(['nama' => 'Budi']);
        $andi = Employee::factory()->forDepartment($edp)->create(['nama' => 'Andi']);

        // Cocok pencarian + departemen + status
        $this->assignedAsset(['asset_code' => 'PC-TARGET'], $budi);
        // Cocok pencarian, beda departemen
        $this->assignedAsset(['asset_code' => 'PC-OTHER-DEPT'], $andi);
        // Beda status, departemen sama
        Asset::factory()->create([
            'asset_code' => 'PC-SAME-DEPT-AVAILABLE',
            'brand' => 'Lenovo',
            'status' => AssetStatus::Available,
        ]);

        $this->actingAs($this->admin())
            ->get(route('assets.index', [
                'q' => 'PC-',
                'status' => AssetStatus::Assigned->value,
                'department_id' => $rnd->id,
            ]))
            ->assertOk()
            ->assertSee('PC-TARGET')
            ->assertDontSee('PC-OTHER-DEPT')
            ->assertDontSee('PC-SAME-DEPT-AVAILABLE');
    }

    public function test_combined_filters_keep_query_string_on_pagination(): void
    {
        $rnd = Department::create(['nama_dept' => 'RnD']);

        // 25 aset agar terpicu halaman kedua.
        for ($i = 1; $i <= 25; $i++) {
            $this->assignedAsset(
                ['asset_code' => sprintf('PC-%04d', $i)],
                Employee::factory()->forDepartment($rnd)->create()
            );
        }

        $response = $this->actingAs($this->admin())
            ->get(route('assets.index', ['status' => AssetStatus::Assigned->value, 'department_id' => $rnd->id]));

        $response->assertOk();
        $this->assertStringContainsString(
            'status='.AssetStatus::Assigned->value,
            $response->getContent()
        );
    }

    public function test_index_shows_current_holder_and_status_badge(): void
    {
        $rnd = Department::create(['nama_dept' => 'RnD']);
        $budi = Employee::factory()->forDepartment($rnd)->create(['nama' => 'Budi']);

        $this->assignedAsset(['asset_code' => 'PC-BUDI'], $budi);

        $this->actingAs($this->admin())
            ->get(route('assets.index'))
            ->assertOk()
            ->assertSee('PC-BUDI')
            ->assertSee('Budi')
            ->assertSee('Assigned');
    }
}
