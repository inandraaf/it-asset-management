<?php

namespace App\Http\Controllers;

use App\Models\AssetMaintenance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Daftar riwayat pemeliharaan aset (lint maintenance).
 *
 * @see dokumentasi/16-pemeliharaan-dan-rincian-akses.md
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

        return view('maintenance.index', compact('items', 'assets'));
    }
}
