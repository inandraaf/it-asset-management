<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @see dokumentasi/05-master-data.md §2
 */
class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_view_employee_index(): void
    {
        $employee = Employee::factory()->create(['nama' => 'Budi']);

        $this->actingAs($this->admin())
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Budi')
            ->assertSee($employee->nip);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('employees.index'))->assertRedirect(route('login'));
    }

    public function test_viewer_cannot_access_write_routes(): void
    {
        $employee = Employee::factory()->create();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('employees.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('employees.store'), [
            'nip' => '999', 'nama' => 'X', 'department_id' => $employee->department_id,
        ])->assertForbidden();
        $this->actingAs($viewer)->get(route('employees.edit', $employee))->assertForbidden();
        $this->actingAs($viewer)->put(route('employees.update', $employee), [
            'nip' => $employee->nip, 'nama' => 'X', 'department_id' => $employee->department_id,
        ])->assertForbidden();
        $this->actingAs($viewer)->delete(route('employees.destroy', $employee))->assertForbidden();

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'nama' => $employee->nama]);
    }

    public function test_admin_can_create_employee_in_a_department(): void
    {
        $department = Department::create(['nama_dept' => 'RnD']);

        $this->actingAs($this->admin())
            ->post(route('employees.store'), [
                'nip' => '20260001',
                'nama' => 'Budi',
                'department_id' => $department->id,
            ])
            ->assertRedirect(route('employees.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('employees', [
            'nip' => '20260001',
            'nama' => 'Budi',
            'department_id' => $department->id,
        ]);
    }

    public function test_employee_input_is_trimmed(): void
    {
        $department = Department::create(['nama_dept' => 'RnD']);

        $this->actingAs($this->admin())
            ->post(route('employees.store'), [
                'nip' => '  20260002  ',
                'nama' => '  Budi  ',
                'department_id' => $department->id,
            ]);

        $this->assertDatabaseHas('employees', ['nip' => '20260002', 'nama' => 'Budi']);
    }

    public function test_duplicate_nip_is_rejected(): void
    {
        $existing = Employee::factory()->create(['nip' => '20260001']);

        $this->actingAs($this->admin())
            ->post(route('employees.store'), [
                'nip' => '20260001',
                'nama' => 'Karyawan Baru',
                'department_id' => $existing->department_id,
            ])
            ->assertSessionHasErrors('nip');
    }

    public function test_update_ignores_current_employee_for_nip_unique_check(): void
    {
        $employee = Employee::factory()->create(['nip' => '20260001']);

        $this->actingAs($this->admin())
            ->put(route('employees.update', $employee), [
                'nip' => '20260001',
                'nama' => 'Nama Baru',
                'department_id' => $employee->department_id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'nama' => 'Nama Baru']);
    }

    public function test_department_must_exist(): void
    {
        $this->actingAs($this->admin())
            ->post(route('employees.store'), [
                'nip' => '20260001',
                'nama' => 'Budi',
                'department_id' => 999999,
            ])
            ->assertSessionHasErrors('department_id');
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs($this->admin())
            ->post(route('employees.store'), [])
            ->assertSessionHasErrors(['nip', 'nama', 'department_id']);
    }

    public function test_admin_can_update_employee(): void
    {
        $employee = Employee::factory()->create(['nama' => 'Budi']);
        $otherDepartment = Department::create(['nama_dept' => 'EDP']);

        $this->actingAs($this->admin())
            ->put(route('employees.update', $employee), [
                'nip' => $employee->nip,
                'nama' => 'Budi Santoso',
                'department_id' => $otherDepartment->id,
            ])
            ->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'nama' => 'Budi Santoso',
            'department_id' => $otherDepartment->id,
        ]);
    }

    public function test_admin_can_delete_employee_without_active_assets(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('employees.destroy', $employee))
            ->assertRedirect(route('employees.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }

    public function test_employee_holding_an_asset_cannot_be_deleted(): void
    {
        $employee = Employee::factory()->create();
        $asset = Asset::factory()->create(['status' => AssetStatus::Assigned]);
        AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'employee_id' => $employee->id,
            'returned_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('employees.destroy', $employee))
            ->assertRedirect(route('employees.index'))
            ->assertSessionHas('error', 'Karyawan masih memegang aset, tarik aset terlebih dahulu.');

        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
    }

    public function test_employee_with_assignment_history_cannot_be_deleted(): void
    {
        $employee = Employee::factory()->create();
        $asset = Asset::factory()->create();
        AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'employee_id' => $employee->id,
            'assigned_date' => now()->subDays(10)->format('Y-m-d'),
            'returned_date' => now()->subDays(2)->format('Y-m-d'),
        ]);

        $this->actingAs($this->admin())
            ->delete(route('employees.destroy', $employee))
            ->assertRedirect(route('employees.index'))
            ->assertSessionHas('error', 'Karyawan memiliki riwayat pemakaian aset sehingga tidak dapat dihapus. Riwayat harus dipertahankan untuk audit.');

        // Riwayat tetap utuh dan karyawan tidak terhapus.
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('asset_assignments', [
            'asset_id' => $asset->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_index_can_search_by_name_or_nip(): void
    {
        $department = Department::create(['nama_dept' => 'RnD']);
        Employee::factory()->forDepartment($department)->create(['nama' => 'Budi', 'nip' => '111']);
        Employee::factory()->forDepartment($department)->create(['nama' => 'Siti', 'nip' => '222']);

        $this->actingAs($this->admin())
            ->get(route('employees.index', ['q' => 'budi']))
            ->assertOk()
            ->assertSee('Budi')
            ->assertDontSee('Siti');

        $this->actingAs($this->admin())
            ->get(route('employees.index', ['q' => '222']))
            ->assertOk()
            ->assertSee('Siti')
            ->assertDontSee('Budi');
    }

    public function test_index_can_filter_by_department(): void
    {
        $rnd = Department::create(['nama_dept' => 'RnD']);
        $edp = Department::create(['nama_dept' => 'EDP']);
        Employee::factory()->forDepartment($rnd)->create(['nama' => 'Budi']);
        Employee::factory()->forDepartment($edp)->create(['nama' => 'Siti']);

        $this->actingAs($this->admin())
            ->get(route('employees.index', ['department_id' => $rnd->id]))
            ->assertOk()
            ->assertSee('Budi')
            ->assertDontSee('Siti');
    }

    public function test_show_displays_active_asset_and_history(): void
    {
        $employee = Employee::factory()->create(['nama' => 'Budi']);

        $activeAsset = Asset::factory()->create(['asset_code' => 'PC-2026-0001', 'status' => AssetStatus::Assigned]);
        AssetAssignment::factory()->create([
            'asset_id' => $activeAsset->id,
            'employee_id' => $employee->id,
            'returned_date' => null,
        ]);

        $returnedAsset = Asset::factory()->create(['asset_code' => 'LT-2026-0002']);
        AssetAssignment::factory()->create([
            'asset_id' => $returnedAsset->id,
            'employee_id' => $employee->id,
            'assigned_date' => now()->subDays(30)->format('Y-m-d'),
            'returned_date' => now()->subDays(5)->format('Y-m-d'),
        ]);

        $this->actingAs($this->admin())
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSee('Budi')
            // Aset aktif harus tampil DI ANTARA heading aset aktif dan heading riwayat.
            ->assertSeeInOrder([
                'Aset yang Sedang Dipegang',
                'PC-2026-0001',
                'Riwayat Pemakaian Aset',
            ])
            // Aset yang sudah dikembalikan hanya muncul di riwayat.
            ->assertSeeInOrder([
                'Riwayat Pemakaian Aset',
                'LT-2026-0002',
            ])
            ->assertSee('Aktif')
            ->assertSee('Selesai');
    }
}
