<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\AssetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alokasi aset: assign, return, transfer.
 *
 * @see dokumentasi/07-alokasi-aset.md
 */
class AssetAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function employee(string $nama = 'Budi'): Employee
    {
        $department = Department::firstOrCreate(['nama_dept' => 'RnD']);

        return Employee::factory()->forDepartment($department)->create(['nama' => $nama]);
    }

    // -------------------------------------------------------------- Akses

    public function test_guest_is_redirected_to_login(): void
    {
        $asset = Asset::factory()->create();

        $this->get(route('assets.assign.create', $asset))->assertRedirect(route('login'));
    }

    public function test_viewer_cannot_assign_return_or_transfer(): void
    {
        $asset = Asset::factory()->create();
        $employee = $this->employee();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('assets.assign.create', $asset))->assertForbidden();
        $this->actingAs($viewer)->post(route('assets.assign.store', $asset), [
            'employee_id' => $employee->id,
            'assigned_date' => now()->format('Y-m-d'),
        ])->assertForbidden();

        $this->assertDatabaseCount('asset_assignments', 0);
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
    }

    // --------------------------------------------------- AC-1: Assign

    public function test_admin_can_assign_available_asset_to_employee(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Available]);
        $employee = $this->employee('Budi');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('assets.assign.store', $asset), [
                'employee_id' => $employee->id,
                'assigned_date' => now()->format('Y-m-d'),
                'notes' => 'Charger lengkap',
            ])
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHas('success');

        $this->assertSame(AssetStatus::Assigned, $asset->fresh()->status);

        $this->assertDatabaseHas('asset_assignments', [
            'asset_id' => $asset->id,
            'employee_id' => $employee->id,
            'returned_date' => null,
            'assigned_by' => $admin->id,
            'notes' => 'Charger lengkap',
        ]);
    }

    public function test_asset_that_is_not_available_cannot_be_assigned(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::InRepair]);
        $employee = $this->employee();

        $this->actingAs($this->admin())
            ->post(route('assets.assign.store', $asset), [
                'employee_id' => $employee->id,
                'assigned_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('asset_id');

        $this->assertSame(AssetStatus::InRepair, $asset->fresh()->status);
        $this->assertDatabaseCount('asset_assignments', 0);
    }

    /**
     * Guard "masih terpasang" hanya tercapai bila ada assignment aktif TETAPI
     * status aset bukan Assigned — keadaan yang seharusnya tidak terjadi,
     * namun justru itu yang dijaga (pertahanan terhadap data tidak konsisten).
     * Status sengaja dibiarkan Available agar guard status tidak keburu menolak.
     */
    public function test_asset_with_active_assignment_but_available_status_cannot_be_assigned(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Available]);
        $current = $this->employee('Budi');
        $other = $this->employee('Andi');

        AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'employee_id' => $current->id,
            'returned_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('assets.assign.store', $asset), [
                'employee_id' => $other->id,
                'assigned_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors([
                'asset_id' => 'Aset masih terpasang pada karyawan lain. Lakukan Return terlebih dahulu.',
            ]);

        // Tidak ada assignment baru yang dibuat.
        $this->assertSame(1, AssetAssignment::where('asset_id', $asset->id)->count());
        $this->assertDatabaseMissing('asset_assignments', [
            'asset_id' => $asset->id,
            'employee_id' => $other->id,
        ]);
    }

    public function test_asset_with_assigned_status_cannot_be_assigned_again(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Assigned]);
        $current = $this->employee('Budi');
        $other = $this->employee('Andi');

        AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'employee_id' => $current->id,
            'returned_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('assets.assign.store', $asset), [
                'employee_id' => $other->id,
                'assigned_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors([
                'asset_id' => 'Aset tidak tersedia untuk di-assign (status saat ini: Assigned).',
            ]);

        $this->assertSame(1, AssetAssignment::where('asset_id', $asset->id)->count());
        $this->assertDatabaseHas('asset_assignments', [
            'asset_id' => $asset->id,
            'employee_id' => $current->id,
            'returned_date' => null,
        ]);
    }

    public function test_future_assign_date_is_rejected(): void
    {
        $asset = Asset::factory()->create();
        $employee = $this->employee();

        $this->actingAs($this->admin())
            ->post(route('assets.assign.store', $asset), [
                'employee_id' => $employee->id,
                'assigned_date' => now()->addDay()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('assigned_date');

        $this->assertDatabaseCount('asset_assignments', 0);
    }

    public function test_assign_form_is_reachable_for_available_asset(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Available]);
        $this->employee('Budi');

        $this->actingAs($this->admin())
            ->get(route('assets.assign.create', $asset))
            ->assertOk()
            ->assertSee('Budi');
    }

    public function test_assign_form_redirects_for_unavailable_asset(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Retired]);

        $this->actingAs($this->admin())
            ->get(route('assets.assign.create', $asset))
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHas('error');
    }

    // --------------------------------------------------- Return

    public function test_return_sets_asset_available_and_closes_assignment(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Assigned]);
        $employee = $this->employee('Budi');
        $assignment = AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'employee_id' => $employee->id,
            'assigned_date' => now()->subDays(10)->format('Y-m-d'),
            'returned_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('assignments.return', $assignment), [
                'returned_date' => now()->format('Y-m-d'),
                'notes' => 'Layar lecet',
            ])
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHas('success');

        $assignment->refresh();
        $this->assertNotNull($assignment->returned_date);
        $this->assertStringContainsString('Layar lecet', (string) $assignment->notes);
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
    }

    public function test_return_date_before_assign_date_is_rejected(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Assigned]);
        $assignment = AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'assigned_date' => now()->subDays(5)->format('Y-m-d'),
            'returned_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('assignments.return', $assignment), [
                'returned_date' => now()->subDays(10)->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('returned_date');

        $this->assertNull($assignment->fresh()->returned_date);
        $this->assertSame(AssetStatus::Assigned, $asset->fresh()->status);
    }

    public function test_returning_the_same_assignment_twice_is_rejected(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Assigned]);
        $assignment = AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'assigned_date' => now()->subDays(10)->format('Y-m-d'),
            'returned_date' => now()->subDays(2)->format('Y-m-d'),
        ]);

        $this->actingAs($this->admin())
            ->post(route('assignments.return', $assignment), [
                'returned_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('assignment');
    }

    // ------------------------------- AC-3: riwayat Budi -> Andi

    public function test_history_records_previous_and_current_holder_after_reassign(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Available]);
        $budi = $this->employee('Budi');
        $andi = $this->employee('Andi');
        $admin = $this->admin();

        // Assign ke Budi, lalu return.
        $this->actingAs($admin)->post(route('assets.assign.store', $asset), [
            'employee_id' => $budi->id,
            'assigned_date' => now()->subDays(60)->format('Y-m-d'),
        ]);

        $budiAssignment = AssetAssignment::where('employee_id', $budi->id)->sole();

        $this->actingAs($admin)->post(route('assignments.return', $budiAssignment), [
            'returned_date' => now()->subDays(30)->format('Y-m-d'),
        ]);

        // Assign ke Andi.
        $this->actingAs($admin)->post(route('assets.assign.store', $asset), [
            'employee_id' => $andi->id,
            'assigned_date' => now()->subDays(20)->format('Y-m-d'),
        ]);

        // Dua baris riwayat.
        $this->assertSame(2, AssetAssignment::where('asset_id', $asset->id)->count());

        // Budi tercatat dengan returned_date, Andi masih aktif.
        $this->assertDatabaseHas('asset_assignments', [
            'asset_id' => $asset->id,
            'employee_id' => $budi->id,
        ]);
        $this->assertNotNull(AssetAssignment::where('employee_id', $budi->id)->sole()->returned_date);

        $this->assertDatabaseHas('asset_assignments', [
            'asset_id' => $asset->id,
            'employee_id' => $andi->id,
            'returned_date' => null,
        ]);

        // Pemegang saat ini = Andi.
        $this->assertSame($andi->id, $asset->fresh()->activeAssignment->employee_id);
        $this->assertSame(AssetStatus::Assigned, $asset->fresh()->status);
    }

    public function test_transfer_creates_two_history_rows_in_one_action(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Assigned]);
        $budi = $this->employee('Budi');
        $andi = $this->employee('Andi');
        $admin = $this->admin();

        AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'employee_id' => $budi->id,
            'assigned_date' => now()->subDays(60)->format('Y-m-d'),
            'returned_date' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('assets.transfer.store', $asset), [
                'employee_id' => $andi->id,
                'transfer_date' => now()->format('Y-m-d'),
                'notes' => 'Mutasi divisi',
            ])
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHas('success');

        // Dua baris: Budi ditutup, Andi aktif.
        $this->assertSame(2, AssetAssignment::where('asset_id', $asset->id)->count());

        $this->assertDatabaseHas('asset_assignments', [
            'asset_id' => $asset->id,
            'employee_id' => $budi->id,
        ]);
        $this->assertNotNull(AssetAssignment::where('employee_id', $budi->id)->sole()->returned_date);

        $this->assertSame($andi->id, $asset->fresh()->activeAssignment->employee_id);
        $this->assertSame(AssetStatus::Assigned, $asset->fresh()->status);
    }

    public function test_transfer_to_current_holder_is_rejected(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Assigned]);
        $budi = $this->employee('Budi');

        AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'employee_id' => $budi->id,
            'returned_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('assets.transfer.store', $asset), [
                'employee_id' => $budi->id,
                'transfer_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('employee_id');

        $this->assertSame(1, AssetAssignment::where('asset_id', $asset->id)->count());
        $this->assertDatabaseHas('asset_assignments', [
            'asset_id' => $asset->id,
            'employee_id' => $budi->id,
            'returned_date' => null,
        ]);
    }

    public function test_transfer_is_rejected_when_asset_is_not_assigned(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Available]);
        $andi = $this->employee('Andi');

        $this->actingAs($this->admin())
            ->post(route('assets.transfer.store', $asset), [
                'employee_id' => $andi->id,
                'transfer_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('asset_id');

        $this->assertDatabaseCount('asset_assignments', 0);
    }

    public function test_transfer_form_excludes_current_holder(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Assigned]);
        $budi = $this->employee('Budi');
        $this->employee('Andi');

        AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'employee_id' => $budi->id,
            'returned_date' => null,
        ]);

        $response = $this->actingAs($this->admin())->get(route('assets.transfer.create', $asset));

        // Pemegang saat ini tetap tampil sebagai info readonly...
        $response->assertOk()->assertSee('Budi')->assertSee('Andi');

        // ...tetapi Budi tidak boleh muncul sebagai opsi dropdown tujuan.
        $response->assertDontSee('value="'.$budi->id.'"', false);
    }

    /**
     * Bila salah satu langkah transfer gagal, tidak boleh ada perubahan
     * parsial: baris lama harus tetap aktif dan status aset tidak berubah.
     */
    public function test_transfer_rolls_back_on_failure(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Assigned]);
        $budi = $this->employee('Budi');

        $assignment = AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'employee_id' => $budi->id,
            'assigned_date' => now()->subDays(30)->format('Y-m-d'),
            'returned_date' => null,
        ]);

        // employee_id tidak ada -> insert assignment gagal di tengah transaksi,
        // setelah langkah return sudah dijalankan.
        try {
            app(AssetAllocationService::class)->transfer($asset, 999999, now(), null);
            $this->fail('Transfer seharusnya gagal.');
        } catch (\Throwable $e) {
            // diharapkan
        }

        $assignment->refresh();
        $this->assertNull($assignment->returned_date, 'Assignment lama tidak boleh ikut ditutup.');
        $this->assertSame(AssetStatus::Assigned, $asset->fresh()->status);
        $this->assertSame(1, AssetAssignment::where('asset_id', $asset->id)->count());
    }
}
