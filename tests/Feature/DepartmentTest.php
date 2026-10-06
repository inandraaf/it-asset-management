<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @see dokumentasi/05-master-data.md §1
 */
class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_view_department_index(): void
    {
        Department::create(['nama_dept' => 'RnD']);

        $this->actingAs($this->admin())
            ->get(route('departments.index'))
            ->assertOk()
            ->assertSee('RnD');
    }

    public function test_viewer_can_view_department_index(): void
    {
        $this->actingAs(User::factory()->viewer()->create())
            ->get(route('departments.index'))
            ->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('departments.index'))->assertRedirect(route('login'));
    }

    public function test_viewer_cannot_access_write_routes(): void
    {
        $department = Department::create(['nama_dept' => 'RnD']);
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('departments.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('departments.store'), ['nama_dept' => 'EP'])->assertForbidden();
        $this->actingAs($viewer)->get(route('departments.edit', $department))->assertForbidden();
        $this->actingAs($viewer)->put(route('departments.update', $department), ['nama_dept' => 'EP'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('departments.destroy', $department))->assertForbidden();

        $this->assertDatabaseMissing('departments', ['nama_dept' => 'EP']);
    }

    public function test_admin_can_create_department(): void
    {
        $this->actingAs($this->admin())
            ->post(route('departments.store'), ['nama_dept' => 'RnD'])
            ->assertRedirect(route('departments.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('departments', ['nama_dept' => 'RnD']);
    }

    public function test_department_name_is_trimmed(): void
    {
        $this->actingAs($this->admin())
            ->post(route('departments.store'), ['nama_dept' => '  RnD  ']);

        $this->assertDatabaseHas('departments', ['nama_dept' => 'RnD']);
    }

    public function test_duplicate_department_name_is_rejected_case_insensitively(): void
    {
        Department::create(['nama_dept' => 'RnD']);

        $this->actingAs($this->admin())
            ->post(route('departments.store'), ['nama_dept' => 'rnd'])
            ->assertSessionHasErrors('nama_dept');

        $this->assertDatabaseCount('departments', 1);
    }

    public function test_department_name_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('departments.store'), ['nama_dept' => ''])
            ->assertSessionHasErrors('nama_dept');
    }

    public function test_department_name_minimum_length(): void
    {
        $this->actingAs($this->admin())
            ->post(route('departments.store'), ['nama_dept' => 'A'])
            ->assertSessionHasErrors('nama_dept');
    }

    public function test_admin_can_update_department(): void
    {
        $department = Department::create(['nama_dept' => 'RnD']);

        $this->actingAs($this->admin())
            ->put(route('departments.update', $department), ['nama_dept' => 'Research'])
            ->assertRedirect(route('departments.index'));

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'nama_dept' => 'Research']);
    }

    public function test_update_ignores_the_current_department_for_unique_check(): void
    {
        $department = Department::create(['nama_dept' => 'RnD']);

        $this->actingAs($this->admin())
            ->put(route('departments.update', $department), ['nama_dept' => 'RnD'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'nama_dept' => 'RnD']);
    }

    public function test_admin_can_delete_empty_department(): void
    {
        $department = Department::create(['nama_dept' => 'CC']);

        $this->actingAs($this->admin())
            ->delete(route('departments.destroy', $department))
            ->assertRedirect(route('departments.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }

    public function test_department_with_employees_cannot_be_deleted(): void
    {
        $department = Department::create(['nama_dept' => 'RnD']);
        Employee::factory()->forDepartment($department)->create();

        $this->actingAs($this->admin())
            ->delete(route('departments.destroy', $department))
            ->assertRedirect(route('departments.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    public function test_index_can_search_by_name(): void
    {
        Department::create(['nama_dept' => 'EDP']);
        Department::create(['nama_dept' => 'HRGA']);

        $this->actingAs($this->admin())
            ->get(route('departments.index', ['q' => 'edp']))
            ->assertOk()
            ->assertSee('EDP')
            ->assertDontSee('HRGA');
    }
}
