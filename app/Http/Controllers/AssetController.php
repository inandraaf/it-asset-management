<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Http\Requests\Asset\StoreAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Department;
use App\Services\CodeGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD aset (PC & Laptop).
 *
 * @see dokumentasi/06-manajemen-aset.md
 */
class AssetController extends Controller
{
    public function __construct(private readonly CodeGenerator $codeGenerator) {}

    public function index(Request $request): View
    {
        $filter = $this->componentFilter($request);

        // Aset yang baru dibuat ditampilkan di baris pertama SEKALI saja.
        // `pull()` menghapus nilainya, sehingga refresh / kembali ke halaman
        // ini berikutnya otomatis kembali ke urutan kode aset (W3).
        $highlightId = session()->pull('highlight_asset_id');

        $assets = Asset::query()
            ->with([
                'activeAssignment.employee.department',
                // Ringkasan perangkat keras dihitung dari komponen terpasang;
                // di-eager load agar daftar tidak menembak query per baris.
                'activeComponentInstallations.component',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->string('q')).'%';

                $query->where(fn ($w) => $w
                    ->where('asset_code', 'ilike', $term)
                    ->orWhere('hostname', 'ilike', $term)
                    ->orWhere('mac_address', 'ilike', $term)
                    ->orWhere('ip_address', 'ilike', $term)
                    ->orWhere('brand', 'ilike', $term)
                    ->orWhereHas(
                        'activeAssignment.employee',
                        fn ($e) => $e->where('nama', 'ilike', $term)
                    ));
            })
            ->when($request->filled('status'), fn ($query) => $query
                ->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query
                ->where('type', $request->string('type')))
            // Filter departemen mencakup DUA sumber:
            // 1. departemen pemegang (PC/Laptop yang di-assign), dan
            // 2. departemen pemilik aset (Printer yang melekat departemen).
            ->when($request->filled('department_id'), function ($query) use ($request) {
                $deptId = $request->integer('department_id');

                $query->where(fn ($w) => $w
                    ->where('department_id', $deptId)
                    ->orWhereHas('activeAssignment.employee',
                        fn ($e) => $e->where('department_id', $deptId)));
            })
            // Filter komponen terpasang: satu kategori, satu nilai (R2).
            ->when($filter, fn ($query) => $query
                ->where(fn ($w) => $this->applyComponentFilter($w, $filter[0], $filter[1], $filter[2])))
            // Urutan tetap: jenis (PC → Laptop → CCTV → Printer), lalu kode aset.
            // `id` sebagai tie-breaker agar deterministik walau kode sama.
            ->when($highlightId, fn ($query) => $query
                ->orderByRaw('CASE WHEN assets.id = ? THEN 0 ELSE 1 END', [$highlightId]))
            ->orderByRaw(AssetType::sqlSortCase('assets.type'))
            ->orderBy('asset_code')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $departments = Department::orderBy('nama_dept')->get();

        return view('assets.index', [
            'assets' => $assets,
            'departments' => $departments,
            'statuses' => AssetStatus::options(),
            'types' => AssetType::options(),
            // Filter bertingkat: Kategori → Atribut → Nilai (V1).
            'componentFilterTree' => Component::filterTree(),
            'highlightId' => $highlightId,
        ]);
    }

    /**
     * Baca filter komponen dari request.
     *
     * @return array{0: string, 1: string}|null [kategori, nilai]
     *
     * @see dokumentasi/15-feedback-dan-tindak-lanjut.md R2
     */
    /**
     * Baca filter komponen dari request (tiga tingkat).
     *
     * @return array{0: string, 1: string, 2: string} [kategori, atribut, nilai]
     */
    private function componentFilter(Request $request): array
    {
        $category = $request->string('filter_category')->toString();
        $value = $request->string('component_value')->toString();

        if ($category === '' || $value === '') {
            return [];
        }

        // Hanya kategori dengan >1 atribut (Storage) yang meminta pilihan atribut.
        // Kategori lain atributnya tetap, jadi diisi otomatis (V2).
        $attribute = Component::hasMultipleAttributes($category)
            ? $request->string('filter_attribute')->toString()
            : Component::defaultAttribute($category);

        if ($attribute === null || $attribute === '') {
            return [];
        }

        // Validitas kategori/atribut diperiksa di applyComponentFilter.
        return [$category, $attribute, $value];
    }

    /**
     * Terapkan filter komponen pada query aset.
     *
     * - **RAM**: memakai **akumulasi total** per aset, sehingga 1x16GB
     *   terhitung sama dengan 2x8GB (R3).
     * - **Kategori lain** (storage/cpu/motherboard/gpu): mencocokkan atribut
     *   `specs` milik kategori tersebut.
     *
     * Filter dibungkus sebagai subquery pada tabel aset, sehingga kondisi
     * "RAM 16GB **dan** storage SSD" berlaku untuk ASET YANG SAMA (R4).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Asset>  $query
     */
    private function applyComponentFilter($query, string $category, string $attribute, string $value): void
    {
        $def = Component::FILTER_GROUPS[$category]['attributes'][$attribute] ?? null;

        if ($def === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        // Sumber 'total': jumlahkan capacity_mb seluruh komponen kategori ini.
        if ($def['source'] === 'total') {
            $targetMb = Component::parseCapacityMb($value);

            if ($targetMb === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereIn('assets.id', function ($sub) use ($category, $targetMb) {
                $sub->from('component_installations as i')
                    ->join('components as c', 'c.id', '=', 'i.component_id')
                    ->whereNull('i.removed_date')
                    ->whereNull('c.deleted_at')
                    ->where('c.category', $category);

                // Total per aset, mis. RAM 2x8GB = 16GB atau SSD 512GB + HDD 1TB.
                $sub->groupBy('i.asset_id')
                    ->havingRaw('SUM(c.capacity_mb) = ?', [$targetMb])
                    ->select('i.asset_id');
            });

            return;
        }

        $query->whereIn('assets.id', function ($sub) use ($category, $attribute, $value) {
            $sub->from('component_installations as i')
                ->join('components as c', 'c.id', '=', 'i.component_id')
                ->whereNull('i.removed_date')
                ->whereNull('c.deleted_at')
                ->where('c.category', $category)
                ->whereRaw('c.specs->>? = ?', [$attribute, $value])
                ->select('i.asset_id');
        });
    }

    public function create(): View
    {
        return view('assets.create', [
            'types' => AssetType::options(),
            'departments' => Department::orderBy('nama_dept')->get(),
            'specKeys' => Asset::SPEC_KEYS,
            'specLabels' => Asset::specLabels(),
            'requiredSpecKeys' => Asset::REQUIRED_SPEC_KEYS,
        ]);
    }

    public function store(StoreAssetRequest $request): RedirectResponse
    {
        $asset = DB::transaction(function () use ($request) {
            $data = $request->validated();

            // Kode aset SELALU dibuat sistem; form tidak menerima input kode.
            $data['asset_code'] = $this->codeGenerator->next(AssetType::from($data['type']));
            $data['status'] = AssetStatus::Available;
            $data['created_by'] = $request->user()->id;

            return Asset::create($data);
        });

        // Tandai aset ini agar tampil teratas pada kunjungan pertama ke daftar.
        session()->put('highlight_asset_id', $asset->id);

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', 'Aset '.$asset->asset_code.' berhasil ditambahkan.');
    }

    public function show(Asset $asset): View
    {
        // Riwayat dipaginasi: aset yang sering berpindah tangan tidak memuat
        // seluruh baris sekaligus.
        $history = $asset->assignments()
            ->with('employee.department')
            ->paginate(25, ['*'], 'history')
            ->withQueryString();

        $asset->load(['activeAssignment.employee.department', 'creator']);

        $installedComponents = $asset->activeComponentInstallations()
            ->with('component')
            ->orderBy('installed_date')
            ->get();

        return view('assets.show', [
            'asset' => $asset,
            'currentAssignment' => $asset->activeAssignment,
            'history' => $history,
            'installedComponents' => $installedComponents,
        ]);
    }

    public function edit(Asset $asset): View
    {
        return view('assets.edit', [
            'asset' => $asset,
            'statuses' => AssetStatus::options(),
            'specKeys' => Asset::SPEC_KEYS,
            'specLabels' => Asset::specLabels(),
            'requiredSpecKeys' => Asset::REQUIRED_SPEC_KEYS,
        ]);
    }

    public function update(UpdateAssetRequest $request, Asset $asset): RedirectResponse
    {
        $asset->update($request->validated());

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', 'Aset '.$asset->asset_code.' berhasil diperbarui.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        if ($asset->activeAssignment()->exists()) {
            return redirect()
                ->route('assets.show', $asset)
                ->with('error', 'Aset sedang dipegang karyawan. Lakukan Return sebelum menghapus.');
        }

        // K9: komponen harus dilepas lebih dulu.
        if ($asset->hasInstalledComponents()) {
            return redirect()
                ->route('assets.show', $asset)
                ->with('error', 'Aset masih memiliki komponen terpasang. Lepas komponennya terlebih dahulu.');
        }

        $code = $asset->asset_code;
        $asset->delete();

        return redirect()
            ->route('assets.index')
            ->with('success', 'Aset '.$code.' berhasil dihapus. Data masih dapat dipulihkan.');
    }

    public function trashed(): View
    {
        $assets = Asset::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->paginate(20)
            ->withQueryString();

        return view('assets.trashed', compact('assets'));
    }

    public function restore(int $id): RedirectResponse
    {
        $asset = Asset::onlyTrashed()->findOrFail($id);

        // Memulihkan aset berarti kode, MAC, dan IP-nya dipakai kembali. Bila
        // aset aktif lain sudah memakai nilai yang sama, restore harus ditolak
        // agar tidak menabrak partial unique index (deleted_at IS NULL).
        // asset_code wajib dicek juga karena bisa dipakai ulang saat aset ini
        // berada di tong sampah.
        if (Asset::where('asset_code', $asset->asset_code)->exists()) {
            return redirect()
                ->route('assets.trashed')
                ->with('error', 'Aset tidak dapat dipulihkan: kode aset sudah dipakai aset aktif lain.');
        }

        if (Asset::where('mac_address', $asset->mac_address)->exists()) {
            return redirect()
                ->route('assets.trashed')
                ->with('error', 'Aset tidak dapat dipulihkan: MAC Address sudah dipakai aset aktif lain.');
        }

        if ($asset->ip_address && Asset::where('ip_address', $asset->ip_address)->exists()) {
            return redirect()
                ->route('assets.trashed')
                ->with('error', 'Aset tidak dapat dipulihkan: IP Address sudah dipakai aset aktif lain.');
        }

        $asset->restore();

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', 'Aset '.$asset->asset_code.' berhasil dipulihkan.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $asset = Asset::onlyTrashed()->withCount('assignments')->findOrFail($id);

        if ($asset->activeAssignment()->exists()) {
            return redirect()
                ->route('assets.trashed')
                ->with('error', 'Aset masih dipegang karyawan sehingga tidak dapat dihapus permanen.');
        }

        if ($asset->assignments_count > 0) {
            return redirect()
                ->route('assets.trashed')
                ->with('error', 'Aset memiliki riwayat pemakaian. Hapus permanen akan menghapus riwayat tersebut.');
        }

        $code = $asset->asset_code;
        $asset->forceDelete();

        return redirect()
            ->route('assets.trashed')
            ->with('success', 'Aset '.$code.' dihapus permanen.');
    }
}
