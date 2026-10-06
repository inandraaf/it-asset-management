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
     * Ringkasan singkat perangkat keras untuk kolom tabel.
     *
     * Fase 2: dihitung dari **komponen terpasang**, bukan dari `specs`.
     * Memerlukan relasi `activeComponentInstallations.component` sudah dimuat.
     */
    public function hardwareSummary(int $limit = 3): string
    {
        if ($this->hasComponentSummaryOverride()) {
            return $this->componentSummaryOverride;
        }

        $values = $this->activeComponentInstallations
            ->map(fn (ComponentInstallation $i) => $i->component?->fullName())
            ->filter()
            ->values();

        return $values->isEmpty()
            ? '—'
            : $values->take($limit)->implode(' · ');
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
     * Apakah aset boleh di-assign ke karyawan.
     *
     * @see dokumentasi/07-alokasi-aset.md R1
     */
    public function isAssignable(): bool
    {
        return $this->status->isAssignable() && ! $this->activeAssignment()->exists();
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
