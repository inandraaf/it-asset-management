<?php

namespace App\Http\Controllers;

use App\Enums\ComponentStatus;
use App\Http\Requests\Component\BulkComponentRequest;
use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentInstallation;
use App\Services\ComponentAllocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * Operasi komponen secara massal (FB-6).
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-6
 */
class BulkComponentController extends Controller
{
    public function __construct(private readonly ComponentAllocationService $allocation) {}

    /**
     * Form pasang massal: pilih beberapa komponen dari gudang → satu host.
     */
    public function installForm(Asset $asset): View
    {
        return view('parts.bulk-install', [
            'asset' => $asset,
            'components' => Component::query()
                ->where('status', ComponentStatus::InStock)
                ->whereDoesntHave('activeInstallation')
                ->orderBy('category')
                ->orderBy('component_code')
                ->get(),
        ]);
    }

    public function install(BulkComponentRequest $request, Asset $asset): RedirectResponse
    {
        $ids = $request->componentIds();

        $this->allocation->installMany(
            $asset,
            $ids,
            Carbon::parse($request->input('date')),
            $request->input('notes'),
        );

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', sprintf('%d komponen dipasang ke %s.', count($ids), $asset->asset_code));
    }

    /**
     * Form lepas massal: pilih beberapa komponen terpasang di satu host.
     */
    public function removeForm(Asset $asset): View
    {
        return view('parts.bulk-remove', [
            'asset' => $asset,
            'installations' => $asset->activeComponentInstallations()->with('component')->get(),
        ]);
    }

    public function remove(BulkComponentRequest $request, Asset $asset): RedirectResponse
    {
        $ids = $request->installationIds();

        $this->allocation->removeMany(
            $ids,
            Carbon::parse($request->input('date')),
            $request->input('notes'),
        );

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', sprintf('%d komponen dilepas dari %s.', count($ids), $asset->asset_code));
    }

    /**
     * Form pindah massal: pilih komponen terpasang di satu host → host tujuan.
     */
    public function moveForm(Asset $asset): View
    {
        return view('parts.bulk-move', [
            'asset' => $asset,
            'installations' => $asset->activeComponentInstallations()->with('component')->get(),
            'assets' => Asset::whereKeyNot($asset->id)->orderBy('asset_code')->get(),
        ]);
    }

    public function move(BulkComponentRequest $request, Asset $asset): RedirectResponse
    {
        $ids = $request->installationIds();
        $target = Asset::findOrFail($request->integer('target_asset_id'));

        // Ambil component_id dari installation_id yang dipilih.
        $componentIds = ComponentInstallation::whereIn('id', $ids)
            ->whereNull('removed_date')
            ->pluck('component_id')
            ->all();

        $this->allocation->moveMany(
            $componentIds,
            $target,
            Carbon::parse($request->input('date')),
            $request->input('notes'),
        );

        return redirect()
            ->route('assets.show', $target)
            ->with('success', sprintf('%d komponen dipindahkan ke %s.', count($componentIds), $target->asset_code));
    }
}
