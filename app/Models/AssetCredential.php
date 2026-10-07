<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kredensial akses sebuah aset (Windows, VNC, dsb.).
 *
 * Satu aset dapat punya banyak kredensial — mis. satu PC dengan akun
 * "Admin" dan "User Biasa", plus kredensial VNC.
 *
 * Kata sandi disimpan terenkripsi memakai APP_KEY (cast `encrypted`).
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md S5
 */
class AssetCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'label',
        'username',
        'password',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'password' => 'encrypted',
    ];

    /**
     * Kata sandi tidak pernah ikut serialisasi JSON.
     */
    protected $hidden = [
        'password',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Label ringkas: "Admin (admin.local)".
     */
    public function displayLabel(): string
    {
        return filled($this->username)
            ? $this->label.' ('.$this->username.')'
            : $this->label;
    }
}
