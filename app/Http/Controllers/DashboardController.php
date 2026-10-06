<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Contracts\View\View;

/**
 * Dashboard: ringkasan statistik aset dan alokasi.
 *
 * @see dokumentasi/08-dashboard.md
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        // Satu query agregat, lalu dipetakan di PHP — menghindari 6 count()
        // terpisah sekaligus mencegah N+1.
        $byTypeAndStatus = Asset::query()
            ->selectRaw('type, status, count(*) as total')
            ->groupBy('type', 'status')
            ->get();

        $count = fn (AssetType $type) => (int) $byTypeAndStatus
            ->where('type', $type)
            ->sum('total');

        $countStatus = fn (AssetStatus $status) => (int) $byTypeAndStatus
            ->where('status', $status)
            ->sum('total');

        $stats = [
            'total_pc' => $count(AssetType::PC),
            'total_laptop' => $count(AssetType::Laptop),
            'assigned' => $countStatus(AssetStatus::Assigned),
            'available' => $countStatus(AssetStatus::Available),
            'in_repair' => $countStatus(AssetStatus::InRepair),
            'retired' => $countStatus(AssetStatus::Retired),
            'total_assets' => (int) $byTypeAndStatus->sum('total'),
            'total_employees' => Employee::count(),
            'total_departments' => Department::count(),
        ];

        $byDepartment = Department::query()
            ->withCount([
                'employees',
                'assignments as assigned_assets_count' => fn ($query) => $query
                    ->whereNull('returned_date'),
            ])
            ->orderBy('nama_dept')
            ->get();

        $recentAssignments = AssetAssignment::query()
            ->with(['asset', 'employee.department'])
            ->orderByDesc('assigned_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('dashboard', compact('stats', 'byDepartment', 'recentAssignments'));
    }
}
