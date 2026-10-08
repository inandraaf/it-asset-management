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
     * Kategori yang tersedia sebagai filter, beserta atribut yang bisa dipilih.
     *
     * Struktur dua tingkat: **Kategori → Atribut → Nilai**. Kategori "storage"
     * tidak lagi muncul tiga kali; admin memilih kategori Storage, lalu memilih
     * atribut (Tipe / Kapasitas Total), baru nilainya (sub-umum).
     *
     * `attribute` = kunci atribut; `source` menentukan cara mengambil nilai:
     * - `specs`  : dari key `specs` (mis. tipe storage)
     * - `total`  : jumlah `capacity_mb` seluruh komponen kategori tersebut
     * - `module` : `capacity_mb` per keping
     *
     * @see dokumentasi/15-feedback-dan-tindak-lanjut.md V1
     */
    public const FILTER_GROUPS = [
        'ram' => [
            'label' => 'RAM',
            'attributes' => [
                'total' => ['label' => 'Kapasitas Total', 'source' => 'total'],
            ],
        ],
        'storage' => [
            'label' => 'Storage',
            'attributes' => [
                'type' => ['label' => 'Tipe', 'source' => 'specs', 'key' => 'type'],
                'total' => ['label' => 'Kapasitas Total', 'source' => 'total'],
            ],
        ],
        'cpu' => [
            'label' => 'CPU',
            'attributes' => [
                'series' => ['label' => 'Seri', 'source' => 'specs', 'key' => 'series'],
            ],
        ],
        'motherboard' => [
            'label' => 'Motherboard',
            'attributes' => [
                'chipset' => ['label' => 'Chipset', 'source' => 'specs', 'key' => 'chipset'],
            ],
        ],
        'gpu' => [
            'label' => 'GPU',
            'attributes' => [
                'model' => ['label' => 'Model', 'source' => 'specs', 'key' => 'model'],
            ],
        ],
        'monitor' => [
            'label' => 'Monitor',
            'attributes' => [
                'size' => ['label' => 'Ukuran', 'source' => 'specs', 'key' => 'size'],
            ],
        ],
    ];

    /**
     * Apakah kategori ini punya lebih dari satu atribut, sehingga dropdown
     * "Atribut" perlu ditampilkan.
     *
     * Hanya Storage yang demikian (Tipe **dan** Kapasitas). Kategori lain
     * atributnya tetap, jadi dropdown Atribut disembunyikan.
     *
     * @see dokumentasi/15-feedback-dan-tindak-lanjut.md V2
     */
    public static function hasMultipleAttributes(string $category): bool
    {
        return count(self::FILTER_GROUPS[$category]['attributes'] ?? []) > 1;
    }

    /**
     * Atribut default sebuah kategori (yang pertama didefinisikan).
     */
    public static function defaultAttribute(string $category): ?string
    {
        $attributes = self::FILTER_GROUPS[$category]['attributes'] ?? [];

        return $attributes === [] ? null : array_key_first($attributes);
    }

    /**
     * Daftar filter untuk dropdown bertingkat.
     *
     * Bentuk: `[kategori => ['label' => ..., 'attributes' => [atribut => ['label'=>..., 'values'=>[...]]]]]`
     * Atribut tanpa nilai (mis. belum ada data) dihilangkan.
     *
     * @return array<string, array{label: string, attributes: array<string, array{label: string, values: array<int, string>}>}>
     */
    public static function filterTree(): array
    {
        $tree = [];

        foreach (self::FILTER_GROUPS as $category => $group) {
            $attributes = [];

            foreach ($group['attributes'] as $key => $def) {
                $values = static::filterValues($category, $def);

                if ($values !== []) {
                    $attributes[$key] = [
                        'label' => $def['label'],
                        'values' => $values,
                    ];
                }
            }

            if ($attributes !== []) {
                $tree[$category] = [
                    'label' => $group['label'],
                    'attributes' => $attributes,
                ];
            }
        }

        return $tree;
    }

    /**
     * Nilai untuk satu definisi filter.
     *
     * @param  array{source: string, key?: string}  $def
     * @return array<int, string>
     */
    private static function filterValues(string $category, array $def): array
    {
        return match ($def['source']) {
            'specs' => static::attributeFilterValues($category, $def['key']),
            'total' => static::totalCapacityOptions($category),
            'module' => static::moduleCapacityOptions($category),
            default => [],
        };
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
     * Total kapasitas unik per aset untuk sebuah kategori.
     *
     * RAM: 2x8GB → 16GB. Storage: SSD 512GB + HDD 1TB → 1536GB.
     *
     * @return array<int, string>
     */
    public static function totalCapacityOptions(string $category): array
    {
        $rows = DB::table('component_installations as i')
            ->join('components as c', 'c.id', '=', 'i.component_id')
            ->whereNull('i.removed_date')
            ->whereNull('c.deleted_at')
            ->where('c.category', $category)
            ->whereNotNull('c.capacity_mb')
            ->groupBy('i.asset_id')
            ->selectRaw('SUM(c.capacity_mb) AS total_mb')
            ->pluck('total_mb');

        return static::formatCapacityList($rows);
    }

    /**
     * Kapasitas per keping untuk sebuah kategori (tanpa penjumlahan).
     *
     * @return array<int, string>
     */
    public static function moduleCapacityOptions(string $category): array
    {
        $rows = static::query()
            ->where('category', $category)
            ->whereNotNull('capacity_mb')
            ->selectRaw('DISTINCT capacity_mb AS value')
            ->pluck('value');

        return static::formatCapacityList($rows);
    }

    /**
     * Ubah daftar MB menjadi label unik terurut.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $rows
     * @return array<int, string>
     */
    private static function formatCapacityList($rows): array
    {
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
