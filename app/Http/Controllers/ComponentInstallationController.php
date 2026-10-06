<?php

namespace App\Http\Controllers;

use App\Enums\ComponentStatus;
use App\Http\Requests\Component\AttachComponentRequest;
use App\Http\Requests\Component\InstallComponentRequest;
use App\Http\Requests\Component\MoveComponentRequest;
use App\Http\Requests\Component\RemoveComponentRequest;
use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentInstallation;
use App\Services\ComponentAllocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * Pemasangan & pelepasan komponen pada host.
 *
 * Pemasangan dimulai dari sisi **host** (pilih komponen yang akan dipasang),
 * pemindahan dimulai dari sisi **komponen** (pilih host tujuan).
 *
 * @see dokumentasi/14-manajemen-komponen.md §13
 */
class ComponentInstallationController extends Controller
{
    public function __construct(private readonly ComponentAllocationService $allocation) {}

    /**
     * Form pilih host untuk memasang komponen ini (dari sisi komponen).
     */
    public function attachCreate(Component $component): View|RedirectResponse
    {
        if (! $component->isInstallable()) {
            return redirect()
                ->route('components.show', $component)
                ->with('error', $this->unavailableMessage($component));
        }

        return view('parts.attach', [
            'part' => $component,
            'assets' => $this->hostOptions(),
        ]);
    }

    public function attach(AttachComponentRequest $request, Component $component): RedirectResponse
    {
        $asset = Asset::findOrFail($request->integer('asset_id'));

        $this->allocation->install(
            $component,
            $asset,
            Carbon::parse($request->input('installed_date')),
            $request->input('notes'),
        );

        return redirect()
            ->route('components.show', $component)
            ->with('success', sprintf(
                'Komponen %s dipasang ke %s.',
                $component->component_code,
                $asset->asset_code
            ));
    }

    /**
     * Form pilih komponen untuk dipasang ke host ini.
     */
    public function create(Asset $asset): View
    {
        return view('parts.install', [
            'asset' => $asset,
            'components' => $this->installableComponents(),
        ]);
    }

    public function store(InstallComponentRequest $request, Asset $asset): RedirectResponse
    {
        $component = Component::findOrFail($request->integer('component_id'));

        $this->allocation->install(
            $component,
            $asset,
            Carbon::parse($request->input('installed_date')),
            $request->input('notes'),
        );

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', sprintf(
                'Komponen %s dipasang ke %s.',
                $component->component_code,
                $asset->asset_code
            ));
    }

    public function remove(RemoveComponentRequest $request, ComponentInstallation $installation): RedirectResponse
    {
        $component = $installation->component;

        // Simpan host asal sebelum pelepasan, untuk menentukan tujuan kembali.
        $originAssetId = $installation->asset_id;

        $this->allocation->remove(
            $installation,
            Carbon::parse($request->input('removed_date')),
            $request->input('notes'),
        );

        $message = 'Komponen '.$component->component_code.' telah dilepas dan kembali ke gudang.';

        // Bila dilepas dari detail aset, kembali ke aset itu (daftarnya ikut terbarui).
        if ($request->string('from')->toString() === 'asset' && $originAssetId) {
            return redirect()->route('assets.show', $originAssetId)->with('success', $message);
        }

        return redirect()->route('components.show', $component)->with('success', $message);
    }

    /**
     * Form pilih host tujuan untuk memindahkan komponen.
     */
    public function moveCreate(Component $component): View|RedirectResponse
    {
        $current = $component->activeInstallation;

        if (! $current) {
            return redirect()
                ->route('components.show', $component)
                ->with('error', 'Komponen ini tidak sedang terpasang. Gunakan fitur Pasang.');
        }

        return view('parts.move', [
            'part' => $component,
            'current' => $current,
            'assets' => $this->hostOptions($current->asset_id),
            // Form ini bisa dibuka dari detail aset maupun detail komponen;
            // tombol Batal & redirect kembali mengikuti asalnya.
            'backUrl' => $this->backUrl($component, $current),
        ]);
    }

    public function move(MoveComponentRequest $request, Component $component): RedirectResponse
    {
        $target = Asset::findOrFail($request->integer('asset_id'));

        $this->allocation->move(
            $component,
            $target,
            Carbon::parse($request->input('move_date')),
            $request->input('notes'),
        );

        $message = 'Komponen '.$component->component_code.' dipindahkan ke '.$target->asset_code.'.';

        // Kembali ke halaman asal; bila dari detail aset, ke aset itu.
        if ($from = $this->sourceAsset($request)) {
            return redirect()->route('assets.show', $from)->with('success', $message);
        }

        return redirect()->route('components.show', $component)->with('success', $message);
    }

    /**
     * URL kembali berdasarkan parameter `from` (asset|component).
     */
    private function backUrl(Component $component, ComponentInstallation $current): string
    {
        $from = request()->string('from')->toString();

        if ($from === 'asset' && $current->asset_id) {
            return route('assets.show', $current->asset_id);
        }

        return route('components.show', $component);
    }

    /**
     * Aset asal bila form pemindahan dibuka dari detail aset.
     */
    private function sourceAsset(\Illuminate\Http\Request $request): ?Asset
    {
        if ($request->string('from')->toString() !== 'asset') {
            return null;
        }

        $assetId = $request->integer('from_asset_id');

        return $assetId ? Asset::find($assetId) : null;
    }

    /**
     * Komponen yang siap dipasang (In Stock, belum terpasang).
     */
    private function installableComponents()
    {
        return Component::query()
            ->where('status', ComponentStatus::InStock)
            ->whereDoesntHave('activeInstallation')
            ->orderBy('category')
            ->orderBy('component_code')
            ->get();
    }

    /**
     * Host yang bisa dipilih sebagai tujuan pindah.
     */
    private function hostOptions(?int $excludeAssetId = null)
    {
        return Asset::query()
            ->when($excludeAssetId, fn ($query) => $query->whereKeyNot($excludeAssetId))
            ->orderBy('asset_code')
            ->get();
    }

    /**
     * Pesan menjelaskan kenapa komponen tidak bisa dipasang.
     */
    private function unavailableMessage(Component $component): string
    {
        if ($component->activeInstallation()->exists()) {
            return 'Komponen masih terpasang di host lain. Lakukan Lepas terlebih dahulu.';
        }

        return 'Komponen tidak tersedia untuk dipasang (status saat ini: '.$component->status->value.').';
    }
}
