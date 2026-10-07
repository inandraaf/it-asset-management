<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * 'role' sengaja TIDAK fillable agar tidak bisa dinaikkan lewat mass
     * assignment. Set role hanya lewat assignRole().
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'role' => UserRole::class,
    ];

    /**
     * Aset yang dibuat oleh user ini.
     */
    public function createdAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'created_by');
    }

    /**
     * Alokasi aset yang diproses oleh user ini.
     */
    public function processedAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class, 'assigned_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isViewer(): bool
    {
        return $this->role === UserRole::Viewer;
    }

    /**
     * Set role secara eksplisit (jalur server-side terkontrol).
     *
     * Role tidak mass-assignable, sehingga pemberian hak Admin harus
     * melewati method ini atau forceFill pada kode tepercaya.
     */
    public function assignRole(UserRole $role): static
    {
        $this->forceFill(['role' => $role])->save();

        return $this;
    }
}
