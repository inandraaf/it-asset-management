<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rekaman pemasangan komponen pada sebuah host (PC/Laptop).
 *
 * @see dokumentasi/14-manajemen-komponen.md §4.2
 */
class ComponentInstallation extends Model
{
    use HasFactory;

    protected $fillable = [
        'component_id',
        'asset_id',
        'installed_date',
        'removed_date',
        'notes',
        'installed_by',
    ];

    protected $casts = [
        'installed_date' => 'date',
        'removed_date' => 'date',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    /**
     * Apakah pemasangan ini masih berjalan.
     */
    public function isActive(): bool
    {
        return $this->removed_date === null;
    }

    /**
     * Durasi pemasangan dalam hari (sampai hari ini bila masih aktif).
     */
    public function durationInDays(): int
    {
        return $this->installed_date->diffInDays($this->removed_date ?? now());
    }
}
