<?php

namespace App\Http\Controllers;

use App\Models\ComponentInstallation;
use App\Models\AssetMaintenance;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Daftar riwayat pemeliharaan aset (lint maintenance).
 */
class MaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $items = AssetMaintenance::query()
            ->when($request->integer('asset_id'), fn ($q, int $id) => $q->where('asset_id', $id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $assets = \App\Models\Asset::select(['id', 'asset_code', 'type'])->orderBy('asset_code')->get();
        $filterAsset = (int) ($request->query('asset_id', 0));

        return view('maintenance.index', compact('items', 'assets', 'filterAsset'));
    }

    public function create(): View
    {
        $assignments = AssetAssignment::with(['asset', 'employee'])->whereNull('returned_date')->get();
        $employees = Employee::orderBy('nama')->get();
        $components = Component::all();

        $assetComponents = [];
        foreach ($assignments as $a) {
            $installs = ComponentInstallation::where('asset_id', $a->asset_id)
                ->whereNull('removed_date')
                ->with('component')
                ->get();
            $assetComponents[$a->asset_id] = [
                'asset_id' => $a->asset_id,
                'employee_id' => $a->employee_id,
                'asset_code' => $a->asset->asset_code ?? null,
                'components' => $installs->map(fn ($ci) => [
                    'id' => $ci->component_id,
                    'component_code' => $ci->component->component_code ?? null,
                ])->values()->all(),
            ];
        }

        return view('maintenance.create', compact('assignments', 'employees', 'components', 'assetComponents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'asset_id' => 'required|exists:assets,id',
            'performed_at' => 'required|date',
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric|min:0',
            'component_id' => 'nullable|exists:components,id',
        ]);

        // Ensure asset assigned to selected employee
        $assignment = AssetAssignment::where('asset_id', $validated['asset_id'])
            ->where('employee_id', $validated['employee_id'])
            ->whereNull('returned_date')
            ->first();
        if (! $assignment) {
            return back()->withInput()->withErrors(['asset_id' => 'Aset tidak dialokasikan ke karyawan tersebut.']);
        }
        // Verify component installed on asset if provided
        if ($validated['component_id']) {
            $installed = ComponentInstallation::where('asset_id', $validated['asset_id'])
                ->where('component_id', $validated['component_id'])
                ->whereNull('removed_date')
                ->exists();
            if (! $installed) {
                return back()->withInput()->withErrors(['component_id' => 'Komponen tidak terpasang pada aset ini.']);
            }
        }
        AssetMaintenance::create([
            'asset_id' => $validated['asset_id'],
            'performed_at' => $validated['performed_at'],
            'description' => $validated['description'],
            'cost' => $validated['cost'],
            'component_id' => $validated['component_id'],
            'performed_by' => Auth::id(),
        ]);

        return redirect()->route('maintenance.index')->with('success', 'Maintenance berhasil ditambah.');
    }
}
