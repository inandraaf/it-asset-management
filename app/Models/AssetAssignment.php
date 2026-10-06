<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'employee_id',
        'assigned_date',
        'returned_date',
        'notes',
        'assigned_by',
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'returned_date' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Assignment yang masih berjalan.
     */
    public function isActive(): bool
    {
        return $this->returned_date === null;
    }

    /**
     * Durasi pemakaian dalam hari (sampai hari ini bila masih aktif).
     */
    public function durationInDays(): int
    {
        return $this->assigned_date->diffInDays($this->returned_date ?? now());
    }
}
