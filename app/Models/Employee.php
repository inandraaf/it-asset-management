<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'nip',
        'nama',
        'department_id',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * Assignment yang masih berjalan (aset belum dikembalikan).
     */
    public function activeAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->whereNull('returned_date');
    }

    /**
     * Apakah karyawan masih memegang minimal satu aset aktif.
     */
    public function hasActiveAssets(): bool
    {
        return $this->activeAssignments()->exists();
    }
}
