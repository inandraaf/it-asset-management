<?php

namespace App\Http\Controllers;

use App\Models\AssetMaintenance;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

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
        
        return view('maintenance.create', compact('assignments', 'employees', 'components'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'performed_at' => 'required|date',
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric|min:0',
            'component_id' => 'nullable|exists:components,id',
        ]);

        AssetMaintenance::create($request->all());

        return redirect()->route('maintenance.index')->with('success', 'Maintenance berhasil ditambah.');
    }
}
