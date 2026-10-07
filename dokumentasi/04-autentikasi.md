# 04 — Autentikasi & Peran

## 1. Ketentuan

- Admin IT **wajib login** sebelum mengakses dashboard.
- Menggunakan **Laravel Breeze** (sudah terpasang di proyek).
- Semua route selain auth dilindungi middleware `auth` (+ `verified` bila email verification diaktifkan).

## 2. Route Bawaan Breeze

| Method | URI | Nama | Fungsi |
| --- | --- | --- | --- |
| GET | `/login` | `login` | Form login |
| POST | `/login` | `login` | Proses login |
| POST | `/logout` | `logout` | Logout |
| GET | `/forgot-password` | `password.request` | Lupa password |
| GET | `/reset-password/{token}` | `password.reset` | Reset password |
| GET | `/verify-email` | `verification.notice` | Verifikasi email |

> **Registrasi publik DIMATIKAN.** Route `register` sudah dikomentari di `routes/auth.php` beserta `RegisteredUserController`. Akun hanya dibuat oleh Admin IT lewat seeder atau halaman manajemen user. Untuk mengaktifkan sementara, buka komentar pada kedua baris tersebut.
>
> Registrasi yang terbuka pada sistem internal adalah lubang privilege escalation: siapa pun yang bisa menjangkau halaman itu dapat membuat akun sendiri.

## 2b. Login Memakai Username (Fase 3, FB-1) ✅

Sistem ini praktis dipakai **satu akun Admin IT**, sehingga seluruh jalur berbasis email
dihapus dan login memakai **username**.

### Yang berlaku sekarang

| Aspek | Ketentuan |
| --- | --- |
| Identitas login | `username` (unik, lowercase, case-insensitive saat login) |
| Kolom `email` | **Dihapus** beserta `email_verified_at` |
| Registrasi publik | Dimatikan |
| Lupa kata sandi | **Tidak ada** form; diganti perintah CLI |
| Verifikasi email | **Tidak ada** (middleware `verified` dilepas dari route) |
| Konfirmasi kata sandi | **Tidak ada** |

### Route yang tersisa

| Method | URI | Nama | Fungsi |
| --- | --- | --- | --- |
| GET | `/login` | `login` | Form login |
| POST | `/login` | `login` | Proses login |
| PUT | `/password` | `password.update` | Ganti kata sandi sendiri (setelah login) |
| POST | `/logout` | `logout` | Logout |

Route berikut **sengaja tidak ada** (404): `register`, `password.request`, `password.email`,
`password.reset`, `verification.notice`, `verification.verify`, `password.confirm`.

### Jika Admin IT Lupa Kata Sandi

Operator dengan akses server menjalankan:

```bash
# Tanya interaktif
php artisan user:reset-password admin

# Langsung set
php artisan user:reset-password admin --password="RahasiaBaru123"

# Buat kata sandi acak kuat (ditampilkan sekali)
php artisan user:reset-password admin --generate
```

Bila username salah, perintah menampilkan daftar username yang tersedia.

> Jalur ini lebih aman daripada form reset publik: hanya bisa dijalankan oleh yang punya
> akses ke server.

## 2c. Manajemen Akun (S3) ✅

Admin IT adalah **superadmin** yang mengelola akun. Halaman `/users` (hanya admin):

- CRUD user dengan role **Admin IT** atau **Viewer** (read-only).
- Viewer tidak dapat mengakses halaman ini (403).
- Reset kata sandi user; kosongkan bila tidak ingin mengubah.
- **Tidak dapat menghapus akun sendiri.**
- **Tidak dapat menghapus Admin IT terakhir** (sistem tidak boleh terkunci).
- Menghapus user tidak menghapus aset/riwayat; kolom audit dikosongkan.

> Tujuan: bila Admin IT tidak tersedia, anggota tim EDP lain yang diberi akses dapat
> memantau aset.

## 3. Role

Role disimpan di kolom `users.role` dengan nilai `admin` atau `viewer`. **Default kolom adalah `viewer`** (least privilege).

```php
// app/Enums/UserRole.php
enum UserRole: string
{
    case Admin = 'admin';
    case Viewer = 'viewer';
}
```

```php
// app/Models/User.php
protected $casts = ['role' => UserRole::class];

public function isAdmin(): bool
{
    return $this->role === UserRole::Admin;
}

/**
 * Role TIDAK fillable; hanya bisa diubah lewat jalur server-side terkontrol.
 */
public function assignRole(UserRole $role): static
{
    $this->forceFill(['role' => $role])->save();

    return $this;
}
```

### Aturan Keamanan Role

| Aturan | Alasan |
| --- | --- |
| `role` tidak ada di `$fillable` | Mencegah `User::create($request->all())` menaikkan hak akses |
| Default kolom `viewer` | Akun baru tidak otomatis menjadi Admin |
| Registrasi publik dimatikan | Tidak ada jalur pembuatan akun mandiri |
| Perubahan role hanya lewat `assignRole()` | Titik tunggal yang mudah diaudit |

> Saat membuat user di test, gunakan state eksplisit: `User::factory()->admin()->create()`. Factory default kini menghasilkan **Viewer**.

## 4. Middleware Role

```bash
php artisan make:middleware EnsureUserHasRole
```

```php
// app/Http/Middleware/EnsureUserHasRole.php
public function handle(Request $request, Closure $next, string ...$roles): Response
{
    abort_unless($request->user() && in_array($request->user()->role->value, $roles, true), 403);
    return $next($request);
}
```

Registrasi alias di `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    // ...
    'role' => \App\Http\Middleware\EnsureUserHasRole::class,
];
```

## 5. Aturan Otorisasi per Aksi

| Aksi | Admin | Viewer |
| --- | --- | --- |
| Lihat dashboard | ✅ | ✅ |
| Lihat daftar & detail aset | ✅ | ✅ |
| Tambah / edit / hapus aset | ✅ | ❌ |
| Assign / Return aset | ✅ | ❌ |
| CRUD departemen & karyawan | ✅ | ❌ |

Implementasi:

- Route tulis dibungkus `->middleware('role:admin')`.
- Route baca dibungkus `auth` saja.
- Untuk operasi aset, gunakan `AssetPolicy` bila perlu aturan per-record.

## 6. Seeder Akun

Password **tidak boleh di-hardcode**. Seeder membacanya dari env melalui `config/itam.php`:

```php
// config/itam.php
return [
    'seed_admin_password'  => env('SEED_ADMIN_PASSWORD', 'password'),
    'seed_viewer_password' => env('SEED_VIEWER_PASSWORD'),
];
```

```php
// database/seeders/UserSeeder.php
$adminPassword = (string) config('itam.seed_admin_password');

if ($adminPassword === '') {
    if (app()->isProduction()) {
        throw new RuntimeException('SEED_ADMIN_PASSWORD wajib diisi di environment ini.');
    }

    $adminPassword = Str::password(16);   // hanya untuk lokal
    $this->command?->warn('Password acak admin: '.$adminPassword);
}

$admin = User::updateOrCreate(
    ['username' => 'admin'],
    ['name' => 'Admin IT', 'password' => Hash::make($adminPassword), 'email_verified_at' => now()],
);
$admin->assignRole(UserRole::Admin);

// Viewer punya password SENDIRI, tidak berbagi dengan admin.
$viewerPassword = (string) config('itam.seed_viewer_password');

if ($viewerPassword === '') {
    if (app()->isProduction()) {
        $this->command?->warn('SEED_VIEWER_PASSWORD kosong; akun viewer demo tidak dibuat.');

        return;                                  // production: jangan buat akun demo
    }

    $viewerPassword = $adminPassword;            // lokal: demi kemudahan dev
}
```

### Matriks Perilaku Seeder

| Akun | Env | `local` / `testing` bila env kosong | `production` / `staging` bila env kosong |
| --- | --- | --- | --- |
| `admin` (username) | `SEED_ADMIN_PASSWORD` | Password acak ditampilkan di console | **Seeder berhenti dengan error** |
| `viewer` (username) | `SEED_VIEWER_PASSWORD` | Memakai password admin (mudah dev) | **Akun viewer tidak dibuat** |

> **Kenapa viewer dipisah.** Sebelumnya kedua akun memakai satu password yang sama, sehingga checklist go-live yang hanya menyuruh mengganti password admin akan meninggalkan akun viewer dengan kredensial bersama. Sekarang di production kedua akun tidak pernah berbagi password.
>
> Tambahkan kedua env tersebut ke `.env` lokal Anda. Jangan commit nilainya.

## 7. Acceptance Terkait

- [x] User anonim yang membuka `/dashboard` dialihkan ke `/login`.
- [x] Viewer yang mencoba `POST /assets` mendapat `403`.
- [x] Setelah login, user diarahkan ke `/dashboard`.
- [x] Route `/register` tidak dapat diakses (404).
- [x] Akun baru hasil factory/seeder memiliki `email_verified_at` terisi.
