<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Kolom kode yang dipakai CodeGenerator.
     */
    public const CODE_COLUMN = 'asset_code';

    /**
     * Key spesifikasi yang dikenal sistem (Fase 2).
     *
     * Setelah part fisik pindah ke tabel `components`, `specs` hanya menyimpan
     * atribut non-fisik: **sistem operasi**. Part dikelola lewat modul komponen.
     *
     * @see dokumentasi/14-manajemen-komponen.md §9
     */
    public const SPEC_KEYS = [
        'os',
    ];

    /**
     * Spesifikasi yang wajib diisi minimal.
     *
     * OS tidak wajib: admin boleh mengisi belakangan.
     */
    public const REQUIRED_SPEC_KEYS = [];

    protected $fillable = [
        'asset_code',
        'type',
        'brand',
        'hostname',
        'department_id',
        'mac_address',
        'ip_address',
        'specs',
        'status',
        'created_by',
    ];

    protected $casts = [
        'specs' => 'array',
        'type' => AssetType::class,
        'status' => AssetStatus::class,
    ];

    /**
     * Ringkasan perangkat keras yang dipasok pemanggil (bukan kolom DB).
     * Dipakai agar daftar aset tidak menembak query per baris.
     */
    private ?string $componentSummaryOverride = null;

    /**
     * Seluruh riwayat alokasi, terbaru di atas.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->orderByDesc('assigned_date');
    }

    /**
     * Assignment yang masih berjalan (pemegang saat ini).
     */
    public function activeAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_date');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Departemen pemilik (untuk aset departemen: CCTV/Printer).
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Seluruh riwayat pemasangan komponen pada host ini.
     */
    public function componentInstallations(): HasMany
    {
        return $this->hasMany(ComponentInstallation::class)->orderByDesc('installed_date');
    }

    /**
     * Komponen yang masih terpasang saat ini.
     */
    public function activeComponentInstallations(): HasMany
    {
        return $this->hasMany(ComponentInstallation::class)->whereNull('removed_date');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', AssetStatus::Available);
    }

    public function scopeOfType(Builder $query, AssetType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Label manusiawi untuk tiap key spesifikasi host.
     *
     * @return array<string, string>
     */
    public static function specLabels(): array
    {
        return [
            'os' => 'Sistem Operasi',
        ];
    }

    /**
     * Spesifikasi yang terisi saja, siap ditampilkan sebagai daftar berlabel.
     *
     * @return array<string, string>
     */
    public function filledSpecs(): array
    {
        $labels = self::specLabels();
        $specs = $this->specs ?? [];

        $out = [];

        foreach (self::SPEC_KEYS as $key) {
            $value = $specs[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $out[$labels[$key] ?? $key] = trim($value);
            }
        }

        return $out;
    }

    /**
     * Ringkasan singkat dari `specs` (Fase 1). Setelah Fase 2, view memakai
     * `hardwareSummary()` yang dihitung dari komponen terpasang.
     */
    public function specSummary(int $limit = 3): string
    {
        $values = array_values($this->filledSpecs());

        return $values === []
            ? '—'
            : implode(' · ', array_slice($values, 0, $limit));
    }

    /**
     * Urutan kategori prioritas pada ringkasan perangkat keras.
     *
     * Urutan ini **tetap**: motherboard, CPU, RAM, storage, GPU. Bila salah
     * satu tidak ada, baru diisi kategori lain (monitor, PSU, dsb).
     *
     * @see dokumentasi/15-feedback-dan-tindak-lanjut.md T2
     */
    public const SUMMARY_CATEGORY_ORDER = [
        'motherboard',
        'cpu',
        'ram',
        'storage',
        'gpu',
    ];

    /**
     * Ringkasan berlabel untuk tooltip kolom spesifikasi.
     *
     * Berbeda dari `hardwareSummary()` (yang ringkas), versi ini menampilkan
     * komponen dengan **nama kategorinya**, mis. "CPU: i7-11700 · RAM: 16GB DDR4".
     *
     * @see dokumentasi/15-feedback-dan-tindak-lanjut.md R6
     */
    public function hardwareSummaryDetailed(): string
    {
        $order = self::SUMMARY_CATEGORY_ORDER;

        $installation = $this->activeComponentInstallations
            ->filter(fn (ComponentInstallation $i) => $i->component !== null)
            ->sortBy(fn (ComponentInstallation $i) => array_search(
                $i->component->category->value,
                $order,
                true
            ) === false ? 99 : array_search($i->component->category->value, $order, true));

        $parts = [];

        foreach ($installation as $item) {
            $component = $item->component;
            $label = $component->category->label();
            // Nilai ganda digabung: "RAM: 8GB DDR4 x2".
            $key = $label.': '.$component->essentialSummary();
            $parts[$key] = ($parts[$key] ?? 0) + 1;
        }

        if ($parts === []) {
            return 'Belum ada komponen terpasang.';
        }

        return collect($parts)
            ->map(fn (int $count, string $label) => $count > 1 ? $label.' x'.$count : $label)
            ->implode(' · ');
    }

    /**
     * Ringkasan singkat perangkat keras untuk kolom tabel.
     *
     * Fase 2: dihitung dari **komponen terpasang**, bukan dari `specs`.
     * Memerlukan relasi `activeComponentInstallations.component` sudah dimuat.
     *
     * Fase 3:
     * - **T2**: kategori prioritas tetap (motherboard, CPU, RAM, storage, GPU);
     *   kategori lain hanya muncul bila slot masih tersisa.
     * - **T3/FB-3**: memakai `Component::essentialSummary()` sehingga yang tampil
     *   atribut teknis (kapasitas RAM/disk, seri CPU), bukan merek.
     */
    public function hardwareSummary(int $limit = 4): string
    {
        if ($this->hasComponentSummaryOverride()) {
            return $this->componentSummaryOverride;
        }

        $installations = $this->activeComponentInstallations
            ->filter(fn (ComponentInstallation $i) => $i->component !== null);

        // 1. Kategori prioritas, urut sesuai SUMMARY_CATEGORY_ORDER.
        $priority = [];

        foreach (self::SUMMARY_CATEGORY_ORDER as $category) {
            $values = $installations
                ->filter(fn (ComponentInstallation $i) => $i->component->category->value === $category)
                ->map(fn (ComponentInstallation $i) => $i->component->essentialSummary())
                ->filter()
                ->values();

            if ($values->isNotEmpty()) {
                // Gabungkan nilai identik: 2 keping 8GB DDR4 → "8GB DDR4 x2".
                $priority[] = $values->countBy()
                    ->map(fn (int $count, string $label) => $count > 1 ? $label.' x'.$count : $label)
                    ->values()
                    ->implode(' + ');
            }
        }

        // 2. Kategori lain mengisi slot yang masih tersisa.
        $others = $installations
            ->reject(fn (ComponentInstallation $i) => in_array(
                $i->component->category->value,
                self::SUMMARY_CATEGORY_ORDER,
                true
            ))
            ->map(fn (ComponentInstallation $i) => $i->component->essentialSummary())
            ->filter()
            ->countBy()
            ->map(fn (int $count, string $label) => $count > 1 ? $label.' x'.$count : $label)
            ->values()
            ->all();

        $values = array_slice([...$priority, ...$others], 0, $limit);

        return $values === []
            ? '—'
            : implode(' · ', $values);
    }

    /**
     * Slot ringkasan yang sudah disiapkan pemanggil (mis. dari eager load),
     * agar tabel daftar tidak menembak query per baris.
     */
    public function setComponentSummaryOverride(?string $summary): static
    {
        $this->componentSummaryOverride = $summary;

        return $this;
    }

    private function hasComponentSummaryOverride(): bool
    {
        return $this->componentSummaryOverride !== null;
    }

    /**
     * Ringkasan sistem operasi (satu-satunya sisa `specs` setelah Fase 2).
     */
    public function osLabel(): string
    {
        return $this->specs['os'] ?? '—';
    }

    /**
     * Merek untuk tampilan. PC rakitan sering tidak punya merek.
     */
    public function brandLabel(): string
    {
        return filled($this->brand) ? $this->brand : 'Rakitan';
    }

    /**
     * Kredensial akses aset (Windows, VNC, dsb.) — jumlah bebas.
     *
     * @see dokumentasi/15-feedback-dan-tindak-lanjut.md S5
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(AssetCredential::class)->orderBy('label');
    }

    /**
     * Apakah aset ini menyimpan kredensial akses.
     */
    public function hasCredentials(): bool
    {
        return $this->credentials()->exists();
    }

    /**
     * Apakah aset boleh di-assign ke karyawan.
     *
     * @see dokumentasi/07-alokasi-aset.md R1
     */
    public function isAssignable(): bool
    {
        // Hanya PC/Laptop yang bisa ditugaskan ke karyawan. Printer melekat
        // departemen, CCTV tanpa pemilik (tanggung jawab Admin IT).
        return $this->type->isAssignable()
            && $this->status->isAssignable()
            && ! $this->activeAssignment()->exists();
    }

    /**
     * Apakah host masih punya komponen terpasang.
     *
     * @see dokumentasi/14-manajemen-komponen.md K9
     */
    public function hasInstalledComponents(): bool
    {
        return $this->activeComponentInstallations()->exists();
    }
}
