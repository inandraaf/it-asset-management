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
     * Key spesifikasi yang dikenal sistem, urut sesuai tampilan form.
     *
     * Disimpan di dalam kolom jsonb `specs`, bukan kolom terpisah, sehingga
     * menambah komponen baru cukup menambah entri di sini tanpa migrasi.
     *
     * @see dokumentasi/06-manajemen-aset.md §1
     */
    public const SPEC_KEYS = [
        'cpu',
        'ram',
        'storage',
        'storage_2',
        'gpu',
        'motherboard',
        'psu',
        'casing',
        'os',
        'monitor',
        'keyboard',
        'mouse',
    ];

    /**
     * Spesifikasi yang wajib diisi minimal.
     */
    public const REQUIRED_SPEC_KEYS = ['cpu'];

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

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', AssetStatus::Available);
    }

    public function scopeOfType(Builder $query, AssetType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Label manusiawi untuk tiap key spesifikasi.
     *
     * @return array<string, string>
     */
    public static function specLabels(): array
    {
        return [
            'cpu' => 'CPU',
            'ram' => 'RAM',
            'storage' => 'Storage 1',
            'storage_2' => 'Storage 2',
            'gpu' => 'GPU',
            'motherboard' => 'Motherboard',
            'psu' => 'Power Supply',
            'casing' => 'Casing',
            'os' => 'Sistem Operasi',
            'monitor' => 'Monitor',
            'keyboard' => 'Keyboard',
            'mouse' => 'Mouse',
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
     * Ringkasan singkat untuk kolom tabel (CPU · RAM · Storage).
     */
    public function specSummary(int $limit = 3): string
    {
        $values = array_values($this->filledSpecs());

        return $values === []
            ? '—'
            : implode(' · ', array_slice($values, 0, $limit));
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
}
