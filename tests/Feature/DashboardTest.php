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
 * @see dokumentasi/08-dashboard.md
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_admin_can_view_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertViewIs('dashboard');
    }

    public function test_viewer_can_view_dashboard(): void
    {
        $this->actingAs(User::factory()->viewer()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertViewIs('dashboard');
    }

    public function test_dashboard_shows_the_four_required_summary_labels(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Total PC')
            ->assertSee('Total Laptop')
            ->assertSee('Aset Terpakai')
            ->assertSee('Aset Menganggur');
    }

    public function test_stats_count_assets_by_type_and_status(): void
    {
        // Jenis dipatok eksplisit: factory memilih tipe secara acak.
        Asset::factory()->pc()->create();
        Asset::factory()->pc()->create(['status' => AssetStatus::Assigned]);
        Asset::factory()->pc()->create(['status' => AssetStatus::InRepair]);
        Asset::factory()->laptop()->create();
        Asset::factory()->laptop()->create(['status' => AssetStatus::Retired]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertViewHas('stats', function (array $stats) {
                // 3 PC (1 available, 1 assigned, 1 in repair) + 2 laptop (1 available, 1 retired)
                $this->assertSame(3, $stats['total_pc']);
                $this->assertSame(2, $stats['total_laptop']);
                $this->assertSame(1, $stats['assigned']);
                $this->assertSame(2, $stats['available']);
                $this->assertSame(1, $stats['in_repair']);
                $this->assertSame(1, $stats['retired']);
                $this->assertSame(5, $stats['total_assets']);

                return true;
            });
    }

    public function test_soft_deleted_assets_are_not_counted(): void
    {
        Asset::factory()->pc()->create();
        Asset::factory()->pc()->create()->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertViewHas('stats', function (array $stats) {
                $this->assertSame(1, $stats['total_pc']);
                $this->assertSame(1, $stats['total_assets']);

                return true;
            });
    }

    public function test_stats_change_after_assign_and_return(): void
    {
        $asset = Asset::factory()->pc()->create(['status' => AssetStatus::Available]);
        $employee = Employee::factory()->create();
        $admin = User::factory()->admin()->create();

        // Sebelum assign: 1 menganggur, 0 terpakai.
        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertViewHas('stats', fn (array $s) => $s['available'] === 1 && $s['assigned'] === 0);

        // Assign -> 0 menganggur, 1 terpakai.
        $this->actingAs($admin)->post(route('assets.assign.store', $asset), [
            'employee_id' => $employee->id,
            'assigned_date' => now()->format('Y-m-d'),
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertViewHas('stats', fn (array $s) => $s['available'] === 0 && $s['assigned'] === 1);

        // Return -> kembali ke 1 menganggur, 0 terpakai.
        $assignment = AssetAssignment::where('asset_id', $asset->id)->sole();

        $this->actingAs($admin)->post(route('assignments.return', $assignment), [
            'returned_date' => now()->format('Y-m-d'),
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertViewHas('stats', fn (array $s) => $s['available'] === 1 && $s['assigned'] === 0);
    }

    public function test_department_summary_counts_employees_and_assigned_assets(): void
    {
        $rnd = Department::create(['nama_dept' => 'RnD']);
        $edp = Department::create(['nama_dept' => 'EDP']);

        $budi = Employee::factory()->forDepartment($rnd)->create();
        Employee::factory()->forDepartment($rnd)->create();
        Employee::factory()->forDepartment($edp)->create();

        // Budi memegang 2 aset aktif; 1 aset RnD sudah dikembalikan (tidak dihitung).
        $first = Asset::factory()->create(['status' => AssetStatus::Assigned]);
        $second = Asset::factory()->create(['status' => AssetStatus::Assigned]);
        AssetAssignment::factory()->create(['asset_id' => $first->id, 'employee_id' => $budi->id, 'returned_date' => null]);
        AssetAssignment::factory()->create(['asset_id' => $second->id, 'employee_id' => $budi->id, 'returned_date' => null]);
        AssetAssignment::factory()->create([
            'asset_id' => Asset::factory()->create()->id,
            'employee_id' => $budi->id,
            'assigned_date' => now()->subDays(30)->format('Y-m-d'),
            'returned_date' => now()->subDays(5)->format('Y-m-d'),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertViewHas('byDepartment', function ($departments) use ($rnd, $edp) {
                $rndRow = $departments->firstWhere('id', $rnd->id);
                $edpRow = $departments->firstWhere('id', $edp->id);

                $this->assertSame(2, $rndRow->employees_count);
                $this->assertSame(2, (int) $rndRow->assigned_assets_count);
                $this->assertSame(1, $edpRow->employees_count);
                $this->assertSame(0, (int) $edpRow->assigned_assets_count);

                return true;
            });
    }

    public function test_dashboard_lists_recent_assignments(): void
    {
        $employee = Employee::factory()->create(['nama' => 'Budi']);
        $asset = Asset::factory()->create(['asset_code' => 'PC-2026-0001', 'status' => AssetStatus::Assigned]);

        AssetAssignment::factory()->create([
            'asset_id' => $asset->id,
            'employee_id' => $employee->id,
            'returned_date' => null,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Budi')
            ->assertSee('PC-2026-0001')
            ->assertSee('Alokasi Terbaru');
    }

    /**
     * Kontrak "10 alokasi terbaru" harus benar-benar diuji: urutan menurun
     * berdasarkan assigned_date (lalu id sebagai pemecah seri) dan dipotong
     * tepat 10 baris.
     */
    public function test_recent_assignments_are_limited_to_ten_and_sorted_descending(): void
    {
        $employee = Employee::factory()->create();

        // 12 assignment dengan tanggal menaik; nomor urut tertua = 1.
        $assignments = collect(range(1, 12))->map(function (int $day) use ($employee) {
            $asset = Asset::factory()->create(['status' => AssetStatus::Assigned]);

            return AssetAssignment::factory()->create([
                'asset_id' => $asset->id,
                'employee_id' => $employee->id,
                'assigned_date' => now()->subDays(30)->addDays($day)->format('Y-m-d'),
                'returned_date' => null,
            ]);
        });

        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertViewHas('recentAssignments', function ($recent) use ($assignments) {
                // Tepat 10 baris, bukan 12.
                $this->assertCount(10, $recent);

                // Urutan menurun berdasarkan assigned_date.
                $dates = $recent->pluck('assigned_date')->map(fn ($d) => $d->format('Y-m-d'))->all();
                $sorted = $dates;
                rsort($sorted);
                $this->assertSame($sorted, $dates, 'Alokasi terbaru harus urut menurun berdasarkan assigned_date.');

                // 10 terbaru = nomor urut 12 turun sampai 3; nomor 1 dan 2 tidak termasuk.
                $ids = $recent->pluck('id')->all();
                $this->assertContains($assignments[11]->id, $ids);
                $this->assertContains($assignments[2]->id, $ids);
                $this->assertNotContains($assignments[0]->id, $ids);
                $this->assertNotContains($assignments[1]->id, $ids);

                return true;
            });
    }

    public function test_recent_assignments_break_ties_by_id_descending(): void
    {
        $employee = Employee::factory()->create();
        $sameDate = now()->subDays(5)->format('Y-m-d');

        $first = AssetAssignment::factory()->create([
            'employee_id' => $employee->id,
            'assigned_date' => $sameDate,
            'returned_date' => null,
        ]);
        $second = AssetAssignment::factory()->create([
            'employee_id' => $employee->id,
            'assigned_date' => $sameDate,
            'returned_date' => null,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertViewHas('recentAssignments', function ($recent) use ($first, $second) {
                $this->assertSame(
                    [$second->id, $first->id],
                    $recent->pluck('id')->all(),
                    'Untuk tanggal yang sama, id terbesar harus tampil lebih dulu.'
                );

                return true;
            });
    }

    public function test_dashboard_renders_when_there_is_no_data(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Belum ada alokasi aset')
            ->assertSee('Belum ada departemen');
    }
}
