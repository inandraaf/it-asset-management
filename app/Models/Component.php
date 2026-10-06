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
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'specs' => 'array',
        'category' => ComponentCategory::class,
        'status' => ComponentStatus::class,
    ];

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
     * Label lengkap: merek + model.
     */
    public function fullName(): string
    {
        return trim($this->brand.' '.($this->model ?? ''));
    }
}
