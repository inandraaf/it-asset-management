<?php

namespace App\Http\Controllers;

use App\Enums\ComponentCategory;
use App\Enums\ComponentStatus;
use App\Http\Requests\Component\StoreComponentRequest;
use App\Http\Requests\Component\UpdateComponentRequest;
use App\Models\Component;
use App\Services\CodeGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD komponen (part) komputer.
 *
 * @see dokumentasi/14-manajemen-komponen.md
 */
class ComponentController extends Controller
{
    public function __construct(private readonly CodeGenerator $codeGenerator) {}

    public function index(Request $request): View
    {
        // Komponen baru tampil teratas SEKALI saja (W3); berikutnya urut kode.
        $highlightId = session()->pull('highlight_component_id');

        $components = Component::query()
            ->with('activeInstallation.asset')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->string('q')).'%';

                $query->where(fn ($w) => $w
                    ->where('component_code', 'ilike', $term)
                    ->orWhere('serial_number', 'ilike', $term)
                    ->orWhere('brand', 'ilike', $term)
                    ->orWhere('model', 'ilike', $term));
            })
            ->when($request->filled('category'), fn ($query) => $query
                ->where('category', $request->string('category')))
            ->when($request->filled('status'), fn ($query) => $query
                ->where('status', $request->string('status')))
            // Urutan tetap: kode komponen.
            ->when($highlightId, fn ($query) => $query
                ->orderByRaw('CASE WHEN components.id = ? THEN 0 ELSE 1 END', [$highlightId]))
            ->orderBy('component_code')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('parts.index', [
            'components' => $components,
            'categories' => ComponentCategory::options(),
            'statuses' => ComponentStatus::options(),
            'highlightId' => $highlightId,
        ]);
    }

    public function create(): View
    {
        return view('parts.create', [
            'categories' => ComponentCategory::options(),
            'specMap' => $this->specMap(),
        ]);
    }

    public function store(StoreComponentRequest $request): RedirectResponse
    {
        $component = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $data['component_code'] = $this->codeGenerator->nextComponent(
                ComponentCategory::from($data['category'])
            );
            $data['status'] = ComponentStatus::InStock;
            $data['created_by'] = $request->user()->id;

            return Component::create($data);
        });

        session()->put('highlight_component_id', $component->id);

        return redirect()
            ->route('components.show', $component)
            ->with('success', 'Komponen '.$component->component_code.' berhasil ditambahkan.');
    }

    public function show(Component $component): View
    {
        $history = $component->installations()
            ->with('asset')
            ->paginate(25, ['*'], 'history')
            ->withQueryString();

        $component->load('activeInstallation.asset');

        // Dikirim sebagai `part`, bukan `component`: Blade memakai variabel
        // internal bernama `$component` saat merender <x-app-layout>, sehingga
        // nama itu tidak aman di dalam view.
        return view('parts.show', [
            'part' => $component,
            'currentInstallation' => $component->activeInstallation,
            'history' => $history,
        ]);
    }

    public function edit(Component $component): View
    {
        // Dikirim sebagai `part` agar tidak bentrok dengan variabel internal
        // `$component` milik Blade saat merender layout.
        return view('parts.edit', [
            'part' => $component,
            'statuses' => ComponentStatus::options(),
            'specMap' => $this->specMap(),
        ]);
    }

    public function update(UpdateComponentRequest $request, Component $component): RedirectResponse
    {
        $component->update($request->validated());

        return redirect()
            ->route('components.show', $component)
            ->with('success', 'Komponen '.$component->component_code.' berhasil diperbarui.');
    }

    public function destroy(Component $component): RedirectResponse
    {
        if ($component->activeInstallation()->exists()) {
            return redirect()
                ->route('components.show', $component)
                ->with('error', 'Komponen sedang terpasang. Lakukan Lepas sebelum menghapus.');
        }

        $code = $component->component_code;
        $component->delete();

        return redirect()
            ->route('components.index')
            ->with('success', 'Komponen '.$code.' berhasil dihapus. Data masih dapat dipulihkan.');
    }

    public function trashed(): View
    {
        $components = Component::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->paginate(20)
            ->withQueryString();

        return view('parts.trashed', compact('components'));
    }

    public function restore(int $id): RedirectResponse
    {
        $component = Component::onlyTrashed()->findOrFail($id);

        // Kode & nomor seri bisa dipakai ulang selama komponen terhapus
        // (unique index parsial), sehingga restore harus dicek konfliknya.
        if (Component::where('component_code', $component->component_code)->exists()) {
            return redirect()
                ->route('components.trashed')
                ->with('error', 'Komponen tidak dapat dipulihkan: kode komponen sudah dipakai komponen aktif lain.');
        }

        if ($component->serial_number
            && Component::where('serial_number', $component->serial_number)->exists()) {
            return redirect()
                ->route('components.trashed')
                ->with('error', 'Komponen tidak dapat dipulihkan: nomor seri sudah dipakai komponen aktif lain.');
        }

        $component->restore();

        return redirect()
            ->route('components.show', $component)
            ->with('success', 'Komponen '.$component->component_code.' berhasil dipulihkan.');
    }

    /**
     * Peta key spesifikasi + label + placeholder per kategori, untuk form.
     *
     * @return array<string, array{label: string, keys: array<string, array{label: string, placeholder: string}>}>
     */
    private function specMap(): array
    {
        $map = [];

        foreach (ComponentCategory::cases() as $category) {
            $essential = [];
            $advanced = [];

            foreach ($category->specKeys() as $key) {
                $essential[$key] = [
                    'label' => ComponentCategory::specLabel($key),
                    'placeholder' => $category->specPlaceholders()[$key] ?? '',
                    'options' => ComponentCategory::optionsFor($key, $category),
                ];
            }

            foreach ($category->advancedSpecKeys() as $key) {
                $advanced[$key] = [
                    'label' => ComponentCategory::specLabel($key),
                    'placeholder' => $category->specPlaceholders()[$key] ?? '',
                    'options' => ComponentCategory::optionsFor($key, $category),
                ];
            }

            $map[$category->value] = [
                'label' => $category->label(),
                'keys' => $essential,
                'advanced' => $advanced,
            ];
        }

        return $map;
    }
}
