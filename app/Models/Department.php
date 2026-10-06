<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_dept',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Seluruh assignment aset yang pernah/sedang dipegang karyawan departemen ini.
     * Dipakai untuk menghitung "aset terpakai per departemen" (bukan jumlah karyawan).
     */
    public function assignments(): HasManyThrough
    {
        return $this->hasManyThrough(
            AssetAssignment::class,
            Employee::class,
            'department_id',   // FK di employees
            'employee_id',     // FK di asset_assignments
            'id',              // local key di departments
            'id'               // local key di employees
        );
    }

    /**
     * Karyawan yang masih memegang aset aktif.
     */
    public function employeesWithActiveAssets(): HasMany
    {
        return $this->employees()
            ->whereHas('assignments', fn ($query) => $query->whereNull('returned_date'));
    }
}
