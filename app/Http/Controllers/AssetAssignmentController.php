<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignAssetRequest;
use App\Http\Requests\ReturnAssetRequest;
use App\Http\Requests\TransferAssetRequest;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Services\AssetAllocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * Alokasi aset: assign, return, dan transfer.
 *
 * @see dokumentasi/07-alokasi-aset.md
 */
class AssetAssignmentController extends Controller
{
    public function __construct(private readonly AssetAllocationService $allocation) {}

    public function create(Asset $asset): View|RedirectResponse
    {
        if (! $asset->isAssignable()) {
            return redirect()
                ->route('assets.show', $asset)
                ->with('error', $this->unavailableMessage($asset));
        }

        return view('assets.assign', [
            'asset' => $asset,
            'employees' => $this->employeeOptions(),
        ]);
    }

    public function store(AssignAssetRequest $request, Asset $asset): RedirectResponse
    {
        $assignment = $this->allocation->assign(
            $asset,
            $request->integer('employee_id'),
            Carbon::parse($request->input('assigned_date')),
            $request->input('notes'),
        );

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', sprintf(
                'Aset %s diserahkan ke %s.',
                $asset->asset_code,
                $assignment->employee->nama
            ));
    }

    public function return(ReturnAssetRequest $request, AssetAssignment $assignment): RedirectResponse
    {
        $asset = $assignment->asset;

        $this->allocation->return(
            $assignment,
            Carbon::parse($request->input('returned_date')),
            $request->input('notes'),
        );

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', 'Aset '.$asset->asset_code.' telah dikembalikan ke IT.');
    }

    public function transferCreate(Asset $asset): View|RedirectResponse
    {
        $current = $asset->activeAssignment;

        if (! $current) {
            return redirect()
                ->route('assets.show', $asset)
                ->with('error', 'Aset ini tidak sedang dipegang siapa pun. Gunakan fitur Assign.');
        }

        return view('assets.transfer', [
            'asset' => $asset,
            'current' => $current,
            'employees' => $this->employeeOptions($current->employee_id),
        ]);
    }

    public function transfer(TransferAssetRequest $request, Asset $asset): RedirectResponse
    {
        $previousHolder = $asset->activeAssignment?->employee?->nama;

        $assignment = $this->allocation->transfer(
            $asset,
            $request->integer('employee_id'),
            Carbon::parse($request->input('transfer_date')),
            $request->input('notes'),
        );

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', sprintf(
                'Aset %s dipindahkan dari %s ke %s.',
                $asset->asset_code,
                $previousHolder ?? '—',
                $assignment->employee->nama
            ));
    }

    /**
     * @param  int|null  $excludeEmployeeId  karyawan yang dikecualikan dari pilihan
     */
    private function employeeOptions(?int $excludeEmployeeId = null)
    {
        return Employee::query()
            ->with('department')
            ->when($excludeEmployeeId, fn ($query) => $query->whereKeyNot($excludeEmployeeId))
            ->orderBy('nama')
            ->get();
    }

    private function unavailableMessage(Asset $asset): string
    {
        if ($asset->activeAssignment()->exists()) {
            return 'Aset masih terpasang pada karyawan lain. Lakukan Return terlebih dahulu.';
        }

        return 'Aset tidak tersedia untuk diserahkan (status saat ini: '.$asset->status->label().').';
    }
}
