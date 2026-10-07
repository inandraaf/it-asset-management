<?php

namespace App\Models;

use App\Enums\ComponentCategory;
use App\Enums\ComponentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Komponen (part) komputer sebagai aset tersendiri.
 *
 * @see dokumentasi/14-manajemen-komponen.md
 */
class Component extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** Kolom kode yang dipakai CodeGenerator. */
    public const CODE_COLUMN = 'component_code';

    protected $fillable = [
        'component_code',
        'category',
        'brand',
        'model',
        'serial_number',
        'specs',
        'capacity_mb',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'specs' => 'array',
        'category' => ComponentCategory::class,
        'status' => ComponentStatus::class,
        'capacity_mb' => 'integer',
    ];

    /**
     * Sinkronkan `capacity_mb` dari `specs.capacity` setiap kali disimpan.
     *
     * Kapasitas di specs berupa teks ("8GB"), sedangkan agregasi/filter
     * memerlukan angka. Lihat dokumentasi/15-feedback-dan-tindak-lanjut.md R3.
     */
    protected static function booted(): void
    {
        static::saving(function (self $component) {
            $component->capacity_mb = self::parseCapacityMb($component->specs['capacity'] ?? null);
        });
    }

    /**
     * Ubah teks kapasitas menjadi MB. Mengembalikan null bila tidak terbaca.
     *
     * Contoh: "8GB" → 8192, "512GB" → 524288, "1TB" → 1048576.
     */
    public static function parseCapacityMb(?string $capacity): ?int
    {
        if (! is_string($capacity) || trim($capacity) === '') {
            return null;
        }

        if (! preg_match('/^\s*([\d.]+)\s*(MB|GB|TB)\s*$/i', $capacity, $m)) {
            return null;
        }

        $num = (float) $m[1];

        $mb = match (strtoupper($m[2])) {
            'MB' => $num,
            'GB' => $num * 1024,
            'TB' => $num * 1024 * 1024,
        };

        return (int) round($mb);
    }

    /**
     * Ubah MB kembali menjadi label ringkas ("16GB", "1TB").
     */
    public static function formatCapacityMb(?int $mb): ?string
    {
        if ($mb === null || $mb <= 0) {
            return null;
        }

        if ($mb % (1024 * 1024) === 0) {
            return ($mb / (1024 * 1024)).'TB';
        }

        if ($mb % 1024 === 0) {
            return ($mb / 1024).'GB';
        }

        return $mb.'MB';
    }

    /**
     * Seluruh riwayat pemasangan, terbaru di atas.
     */
    public function installations(): HasMany
    {
        return $this->hasMany(ComponentInstallation::class)->orderByDesc('installed_date');
    }

    /**
     * Pemasangan yang masih berjalan (lokasi komponen saat ini).
     */
    public function activeInstallation(): HasOne
    {
        return $this->hasOne(ComponentInstallation::class)->whereNull('removed_date');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOfCategory(Builder $query, ComponentCategory $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('status', ComponentStatus::InStock);
    }

    /**
     * Apakah komponen boleh dipasang ke host.
     *
     * @see dokumentasi/14-manajemen-komponen.md K2, K3
     */
    public function isInstallable(): bool
    {
        return $this->status->isInstallable() && ! $this->activeInstallation()->exists();
    }

    /**
     * Host tempat komponen terpasang saat ini, bila ada.
     */
    public function currentAsset(): ?Asset
    {
        return $this->activeInstallation?->asset;
    }

    /**
     * Nama lokasi untuk tampilan: kode host atau "Gudang".
     */
    public function locationLabel(): string
    {
        return $this->currentAsset()?->asset_code ?? ($this->status === ComponentStatus::InStock ? 'Gudang' : '—');
    }

    /**
     * Spesifikasi yang terisi saja, berlabel.
     *
     * @return array<string, string>
     */
    public function filledSpecs(): array
    {
        $specs = $this->specs ?? [];
        $out = [];

        foreach ($this->category->specKeys() as $key) {
            $value = $specs[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $out[ComponentCategory::specLabel($key)] = trim($value);
            }
        }

        return $out;
    }

    /**
     * Definisi filter di daftar aset.
     *
     * Tiap filter punya kunci sendiri, sehingga **Storage bisa muncul dua kali**
     * (tipe dan kapasitas) dan keduanya bisa dikombinasikan.
     *
     * `attribute` = key di dalam `specs`; `null` berarti memakai `capacity_mb`.
     *
     * @see dokumentasi/15-feedback-dan-tindak-lanjut.md S2
     */
    public const FILTERS = [
        'ram' => ['category' => 'ram', 'attribute' => null, 'label' => 'RAM (Total)'],
        'storage_type' => ['category' => 'storage', 'attribute' => 'type', 'label' => 'Storage — Tipe'],
        'storage_capacity' => ['category' => 'storage', 'attribute' => null, 'label' => 'Storage — Kapasitas per Keping'],
        'storage_capacity_total' => ['category' => 'storage', 'attribute' => null, 'label' => 'Storage — Kapasitas Total', 'aggregate' => true],
        'cpu' => ['category' => 'cpu', 'attribute' => 'series', 'label' => 'CPU'],
        'motherboard' => ['category' => 'motherboard', 'attribute' => 'chipset', 'label' => 'Motherboard'],
        'gpu' => ['category' => 'gpu', 'attribute' => 'model', 'label' => 'GPU'],
    ];

    /**
     * Daftar opsi filter, diambil dari data aktual.
     *
     * @return array<string, array{label: string, category: string, attribute: ?string, values: array<int, string>}>
     */
    public static function filterOptions(): array
    {
        $options = [];

        foreach (self::FILTERS as $key => $def) {
            $values = $def['attribute'] === null
                ? static::capacityFilterValues($def['category'], $def['aggregate'] ?? false)
                : static::attributeFilterValues($def['category'], $def['attribute']);

            if ($values !== []) {
                $options[$key] = [
                    'label' => $def['label'],
                    'category' => $def['category'],
                    'attribute' => $def['attribute'],
                    'values' => $values,
                ];
            }
        }

        return $options;
    }

    /**
     * Nilai filter berbasis `specs` (mis. tipe storage, seri CPU).
     *
     * @return array<int, string>
     */
    private static function attributeFilterValues(string $category, string $attribute): array
    {
        return static::query()
            ->where('category', $category)
            ->whereRaw('specs->>? IS NOT NULL', [$attribute])
            ->selectRaw('DISTINCT specs->>? AS value', [$attribute])
            ->orderBy('value')
            ->pluck('value')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Nilai filter berbasis kapasitas (`capacity_mb`).
     *
     * - **RAM**: memakai akumulasi total per aset (1x16GB = 2x8GB).
     * - **Storage**: per keping (SSD 512GB + HDD 1TB → muncul "512GB" dan "1TB").
     *
     * @return array<int, string>
     */
    private static function capacityFilterValues(string $category, bool $aggregate = false): array
    {
        // RAM selalu memakai akumulasi total (1x16GB = 2x8GB).
        if ($category === ComponentCategory::Ram->value) {
            return static::ramTotalOptions();
        }

        // Storage mode agregat: total per aset (SSD 512GB + HDD 1TB = 1.5TB).
        if ($aggregate) {
            return static::storageTotalOptions();
        }

        $rows = static::query()
            ->where('category', $category)
            ->whereNotNull('capacity_mb')
            ->selectRaw('DISTINCT capacity_mb AS value')
            ->pluck('value');

        return $rows
            ->map(fn ($mb) => self::formatCapacityMb((int) $mb))
            ->filter()
            ->unique()
            ->sortBy(fn (string $label) => (int) self::parseCapacityMb($label))
            ->values()
            ->all();
    }

    /**
     * Total RAM unik yang benar-benar terpasang pada aset aktif.
     *
     * @return array<int, string>
     */
    public static function ramTotalOptions(): array
    {
        $rows = DB::table('component_installations as i')
            ->join('components as c', 'c.id', '=', 'i.component_id')
            ->whereNull('i.removed_date')
            ->whereNull('c.deleted_at')
            ->where('c.category', ComponentCategory::Ram->value)
            ->whereNotNull('c.capacity_mb')
            ->groupBy('i.asset_id')
            ->selectRaw('SUM(c.capacity_mb) AS total_mb')
            ->pluck('total_mb');

        return $rows
            ->map(fn ($mb) => self::formatCapacityMb((int) $mb))
            ->filter()
            ->unique()
            ->sortBy(fn (string $label) => (int) self::parseCapacityMb($label))
            ->values()
            ->all();
    }

    /**
     * Total storage unik per aset (SSD + HDD dijumlahkan).
     *
     * @return array<int, string>
     */
    public static function storageTotalOptions(): array
    {
        $rows = DB::table('component_installations as i')
            ->join('components as c', 'c.id', '=', 'i.component_id')
            ->whereNull('i.removed_date')
            ->whereNull('c.deleted_at')
            ->where('c.category', ComponentCategory::Storage->value)
            ->whereNotNull('c.capacity_mb')
            ->groupBy('i.asset_id')
            ->selectRaw('SUM(c.capacity_mb) AS total_mb')
            ->pluck('total_mb');

        return $rows
            ->map(fn ($mb) => self::formatCapacityMb((int) $mb))
            ->filter()
            ->unique()
            ->sortBy(fn (string $label) => (int) self::parseCapacityMb($label))
            ->values()
            ->all();
    }

    /**
     * Label lengkap: merek + model.
     */
    public function fullName(): string
    {
        return trim($this->brand.' '.($this->model ?? ''));
    }

    /**
     * Ringkasan **esensial** untuk kolom tabel — atribut teknis, bukan merek.
     *
     * Menjawab pertanyaan operasional Admin IT: "RAM-nya berapa GB?",
     * "disknya berapa besar?", "CPU seri apa?" — tanpa perlu membuka detail.
     *
     * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-3
     */
    public function essentialSummary(): string
    {
        $specs = $this->specs ?? [];
        $get = fn (string $key) => isset($specs[$key]) && trim((string) $specs[$key]) !== ''
            ? trim((string) $specs[$key])
            : null;

        return match ($this->category) {
            ComponentCategory::Cpu => $get('series') ?? $get('base_clock') ?? $this->model ?? $this->brand,
            ComponentCategory::Ram => trim(implode(' ', array_filter([$get('capacity'), $get('type')])))
                ?: $this->fullName(),
            ComponentCategory::Storage => trim(implode(' ', array_filter([$get('capacity'), $get('type')])))
                ?: $this->fullName(),
            ComponentCategory::Gpu => $get('model') ?? $this->model ?? $this->brand,
            ComponentCategory::Motherboard => $get('chipset') ?? $this->model ?? $this->brand,
            ComponentCategory::Psu => $get('wattage') ?? $this->fullName(),
            ComponentCategory::Casing => $get('form_factor') ?? $this->fullName(),
            ComponentCategory::Monitor => $get('size') ?? $this->fullName(),
            ComponentCategory::Keyboard,
            ComponentCategory::Mouse => $get('connection') ?? $this->fullName(),
            ComponentCategory::Other => $this->fullName(),
        };
    }
}
