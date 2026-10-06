# 03 — Desain Database

Database: **PostgreSQL**. Semua nama tabel plural snake_case, semua primary key `id` bigint auto increment, dan semua tabel transaksional memakai `created_at` / `updated_at`.

## 1. ERD

```mermaid
erDiagram
    DEPARTMENTS ||--o{ EMPLOYEES : "memiliki"
    EMPLOYEES   ||--o{ ASSET_ASSIGNMENTS : "menerima"
    ASSETS      ||--o{ ASSET_ASSIGNMENTS : "dialokasikan"
    ASSETS      ||--o{ COMPONENT_INSTALLATIONS : "menampung (host)"
    COMPONENTS  ||--o{ COMPONENT_INSTALLATIONS : "dipasang"
    USERS       ||--o{ ASSETS : "mencatat (created_by)"
    USERS       ||--o{ COMPONENTS : "mencatat (created_by)"

    DEPARTMENTS {
        bigint id PK
        string nama_dept UK
        timestamp created_at
        timestamp updated_at
    }
    EMPLOYEES {
        bigint id PK
        string nip UK
        string nama
        bigint department_id FK
        timestamp created_at
        timestamp updated_at
    }
    ASSETS {
        bigint id PK
        string asset_code UK
        string type
        string brand
        string hostname UK
        string mac_address UK
        string ip_address UK
        jsonb specs
        string status
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    ASSET_ASSIGNMENTS {
        bigint id PK
        bigint asset_id FK
        bigint employee_id FK
        date assigned_date
        date returned_date
        text notes
        bigint assigned_by FK
        timestamp created_at
        timestamp updated_at
    }
    COMPONENTS {
        bigint id PK
        string component_code UK
        string category
        string brand
        string model
        string serial_number UK
        jsonb specs
        string status
        text notes
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    COMPONENT_INSTALLATIONS {
        bigint id PK
        bigint component_id FK
        bigint asset_id FK
        date installed_date
        date removed_date
        text notes
        bigint installed_by FK
        timestamp created_at
        timestamp updated_at
    }
```

> Tabel `components` dan `component_installations` adalah bagian **Fase 2**.
> Desain lengkapnya di [14-manajemen-komponen.md](14-manajemen-komponen.md).

## 2. Tabel `departments`

| Kolom | Tipe | Constraint | Keterangan |
| --- | --- | --- | --- |
| `id` | bigserial | PK | |
| `nama_dept` | varchar(100) | NOT NULL, UNIQUE | Contoh: EDP, HRGA, EP, RnD, CC |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

Data awal (seeder): `EDP`, `HRGA`, `EP`, `RnD`, `CC`.

```php
Schema::create('departments', function (Blueprint $table) {
    $table->id();
    $table->string('nama_dept', 100)->unique();
    $table->timestamps();
});
```

## 3. Tabel `employees`

| Kolom | Tipe | Constraint | Keterangan |
| --- | --- | --- | --- |
| `id` | bigserial | PK | |
| `nip` | varchar(30) | NOT NULL, UNIQUE | Nomor Induk Pegawai (User ID) |
| `nama` | varchar(150) | NOT NULL | Index untuk pencarian |
| `department_id` | bigint | FK → `departments.id`, `restrictOnDelete` | Tidak boleh dihapus jika masih dipakai |
| `created_at` / `updated_at` | timestamp | nullable | |

```php
Schema::create('employees', function (Blueprint $table) {
    $table->id();
    $table->string('nip', 30)->unique();
    $table->string('nama', 150);
    $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
    $table->timestamps();
    $table->index('nama');
});
```

## 4. Tabel `assets`

| Kolom | Tipe | Constraint | Keterangan |
| --- | --- | --- | --- |
| `id` | bigserial | PK | |
| `asset_code` | varchar(30) | NOT NULL, UNIQUE | **Selalu dibuat sistem** (tidak diisi manual) |
| `type` | varchar(10) | NOT NULL, CHECK (`PC`,`Laptop`) | Jenis aset |
| `brand` | varchar(100) | NOT NULL | Merek & model, contoh: Dell OptiPlex 7090 |
| `hostname` | varchar(63) | UNIQUE (parsial), nullable | Nama komputer di jaringan/Windows, disimpan lowercase |
| `mac_address` | varchar(17) | NOT NULL, UNIQUE | Format `AA:BB:CC:DD:EE:FF` |
| `ip_address` | varchar(45) | UNIQUE, nullable | IPv4/IPv6, unik jika diisi |
| `specs` | jsonb | NOT NULL, default `{}` | Komponen komputer — lihat tabel di bawah |
| `status` | varchar(20) | NOT NULL, default `Available` | CHECK 4 nilai status |
| `created_by` | bigint | FK → `users.id`, `nullOnDelete` | Audit pembuat (opsional) |
| `deleted_at` | timestamp | nullable | **Soft delete** — data tidak benar-benar hilang |
| `created_at` / `updated_at` | timestamp | nullable | |

### Key di dalam `specs` (jsonb)

> **Fase 2 sudah berjalan.** Part fisik (cpu, ram, storage, gpu, motherboard, psu, casing,
> monitor, keyboard, mouse) kini menjadi tabel **`components`**, dan `specs` tinggal `os`
> (perangkat lunak, bukan benda fisik). Lihat
> [14-manajemen-komponen.md](14-manajemen-komponen.md) §9.

Setelah Fase 2, `specs` **hanya** menyimpan atribut non-fisik:

| Key | Label tampilan | Wajib |
| --- | --- | --- |
| `os` | Sistem Operasi | — (opsional) |

Daftar key didefinisikan di `Asset::SPEC_KEYS` dan `Asset::specLabels()`.

Contoh isi:

```json
{
  "os": "Windows 11 Pro 64-bit"
}
```

> **Ringkasan perangkat keras** (CPU, RAM, disk, dst.) tidak disimpan di `specs`, melainkan
> **dihitung** dari komponen terpasang: `Asset::hardwareSummary()` membaca relasi
> `activeComponentInstallations.component`. Lihat
> [14-manajemen-komponen.md](14-manajemen-komponen.md) §9.

```php
Schema::create('assets', function (Blueprint $table) {
    $table->id();
    $table->string('asset_code', 30);
    $table->string('type', 10);
    $table->string('brand', 100);
    $table->string('hostname', 63)->nullable();
    $table->string('mac_address', 17);
    $table->string('ip_address', 45)->nullable();
    $table->jsonb('specs')->default('{}');
    $table->string('status', 20)->default('Available');
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->softDeletes();
    $table->timestamps();

    $table->index('status');
    $table->index('type');
});

// Unique index partial: hanya berlaku untuk baris yang belum di-soft delete.
// Dengan begitu MAC/IP bisa dipakai ulang setelah aset lama dihapus (soft).
DB::statement('CREATE UNIQUE INDEX assets_asset_code_unique
    ON assets (asset_code) WHERE deleted_at IS NULL');
DB::statement('CREATE UNIQUE INDEX assets_mac_address_unique
    ON assets (mac_address) WHERE deleted_at IS NULL');
DB::statement('CREATE UNIQUE INDEX assets_ip_address_unique
    ON assets (ip_address) WHERE deleted_at IS NULL AND ip_address IS NOT NULL');
DB::statement('CREATE UNIQUE INDEX assets_hostname_unique
    ON assets (hostname) WHERE deleted_at IS NULL AND hostname IS NOT NULL');
```

Aturan penting PostgreSQL:

- `UNIQUE` pada kolom nullable **mengizinkan banyak `NULL`**, sehingga IP opsional aman.
- Karena memakai **soft delete**, unique index dibuat **partial** (`WHERE deleted_at IS NULL`) agar aset yang sudah dihapus tidak memblokir penggunaan ulang MAC/IP. Ini juga membuat filter `ip_address IS NOT NULL` pada index IP menjadi eksplisit.

### Implikasi `asset_code` yang Unique Parsial

Perhatikan konsekuensi berbeda antara `asset_code` dengan `mac_address`/`ip_address`:

| Kolom | Unique parsial berarti |
| --- | --- |
| `mac_address`, `ip_address` | **Diinginkan** — perangkat fisik boleh dipakai ulang setelah aset lama dihapus |
| `hostname` | **Diinginkan** — nama komputer boleh dipakai ulang setelah PC lama dihapus |
| `asset_code` | **Perlu kehati-hatian** — kode aset adalah identitas; aset baru tidak boleh mewarisi kode aset terhapus |

Karena itu `AssetCodeGenerator` (lihat [06-manajemen-aset.md](06-manajemen-aset.md) §2) **wajib memakai `withTrashed()`** saat mencari nomor urut terakhir. Jika hanya menghitung baris aktif, generator bisa menerbitkan `asset_code` yang sama dengan aset yang sudah di-soft delete dan menimbulkan kebingungan pada laporan serta audit.

## 5. Tabel `asset_assignments`

Tabel ini adalah **log riwayat**. Satu baris = satu periode pemakaian.

| Kolom | Tipe | Constraint | Keterangan |
| --- | --- | --- | --- |
| `id` | bigserial | PK | |
| `asset_id` | bigint | FK → `assets.id`, `cascadeOnDelete` | |
| `employee_id` | bigint | FK → `employees.id`, `restrictOnDelete` | |
| `assigned_date` | date | NOT NULL | Tanggal mulai |
| `returned_date` | date | nullable | `NULL` = masih dipakai |
| `notes` | text | nullable | Catatan kondisi/kelengkapan |
| `assigned_by` | bigint | FK → `users.id`, `nullOnDelete` | Admin yang memproses |
| `created_at` / `updated_at` | timestamp | nullable | |

```php
Schema::create('asset_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
    $table->date('assigned_date');
    $table->date('returned_date')->nullable();
    $table->text('notes')->nullable();
    $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['asset_id', 'returned_date']);
});
```

### Invariant yang harus dijaga aplikasi

> Satu aset hanya boleh punya **maksimal satu** assignment aktif (`returned_date IS NULL`).

Implementasi: partial unique index di PostgreSQL.

```php
DB::statement('CREATE UNIQUE INDEX one_active_assignment_per_asset
    ON asset_assignments (asset_id) WHERE returned_date IS NULL;');
```

## 5b. Tabel `components` (Fase 2)

Komponen (part) sebagai aset tersendiri. Desain lengkap: [14-manajemen-komponen.md](14-manajemen-komponen.md).

| Kolom | Tipe | Constraint | Keterangan |
| --- | --- | --- | --- |
| `id` | bigserial | PK | |
| `component_code` | varchar(30) | NOT NULL, UNIQUE (parsial) | Dibuat sistem, contoh `RAM-2026-0001` |
| `category` | varchar(20) | NOT NULL, CHECK | `cpu`, `ram`, `storage`, `gpu`, `motherboard`, `psu`, `casing`, `monitor`, `keyboard`, `mouse`, `other` |
| `brand` | varchar(100) | NOT NULL | Merek |
| `model` | varchar(150) | nullable | Model |
| `serial_number` | varchar(100) | UNIQUE (parsial), nullable | Nomor seri fisik |
| `specs` | jsonb | NOT NULL, default `{}` | Atribut khas kategori |
| `status` | varchar(20) | NOT NULL, default `In Stock` | CHECK 4 nilai |
| `notes` | text | nullable | |
| `created_by` | bigint | FK → `users.id`, `nullOnDelete` | |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` / `updated_at` | timestamp | nullable | |

## 5c. Tabel `component_installations` (Fase 2)

Satu baris = satu periode pemasangan komponen pada sebuah host.

| Kolom | Tipe | Constraint | Keterangan |
| --- | --- | --- | --- |
| `id` | bigserial | PK | |
| `component_id` | bigint | FK → `components.id`, `cascadeOnDelete` | Komponen (subjek riwayat) |
| `asset_id` | bigint | FK → `assets.id`, `restrictOnDelete` | Host tempat dipasang |
| `installed_date` | date | NOT NULL | |
| `removed_date` | date | nullable | `NULL` = masih terpasang |
| `notes` | text | nullable | |
| `installed_by` | bigint | FK → `users.id`, `nullOnDelete` | |
| `created_at` / `updated_at` | timestamp | nullable | |

Invariant: satu komponen maksimal terpasang di **satu** host pada satu waktu.

```php
DB::statement('CREATE UNIQUE INDEX one_active_install_per_component
    ON component_installations (component_id) WHERE removed_date IS NULL');
```

## 6. tabel `users` (modifikasi)

Tambahkan `role` pada migrasi users atau migrasi terpisah:

```php
// database/migrations/xxxx_add_role_to_users_table.php
Schema::table('users', function (Blueprint $table) {
    // Default 'viewer' = least privilege. Akun baru TIDAK boleh otomatis Admin.
    $table->string('role', 20)->default('viewer')->after('password');
    $table->index('role');
});
```

### Keamanan Kolom `role`

- `role` **tidak dimasukkan ke `$fillable`** pada model `User`, sehingga tidak bisa dinaikkan lewat mass assignment (mis. body request registrasi berisi `role=admin`).
- Pemberian role hanya lewat jalur server-side terkontrol: `User::assignRole()` (memakai `forceFill`) atau seeder.
- Registrasi publik dimatikan; akun dibuat oleh Admin IT. Lihat [04-autentikasi.md](04-autentikasi.md) §2.

## 7. CHECK Constraints (PostgreSQL)

```php
DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_type_check
    CHECK (type IN ('PC','Laptop'))");

DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_status_check
    CHECK (status IN ('Available','Assigned','In Repair','Retired'))");

DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check
    CHECK (role IN ('admin','viewer'))");

DB::statement("ALTER TABLE asset_assignments ADD CONSTRAINT assignment_date_check
    CHECK (returned_date IS NULL OR returned_date >= assigned_date)");

// Fase 2 — komponen
DB::statement("ALTER TABLE components ADD CONSTRAINT components_category_check
    CHECK (category IN ('cpu','ram','storage','gpu','motherboard','psu','casing','monitor','keyboard','mouse','other'))");

DB::statement("ALTER TABLE components ADD CONSTRAINT components_status_check
    CHECK (status IN ('In Stock','Installed','In Repair','Retired'))");

DB::statement("ALTER TABLE component_installations ADD CONSTRAINT component_install_date_check
    CHECK (removed_date IS NULL OR removed_date >= installed_date)");
```

> **Catatan implementasi:** enum **tidak dibuat sebagai PostgreSQL `ENUM type`**, melainkan `varchar` + `CHECK constraint`. Alasannya: mengubah nilai enum PostgreSQL menuntut `ALTER TYPE ... ADD VALUE` yang tidak bisa dijalankan di dalam transaksi, sementara `CHECK` mudah di-`DROP`/re-create. Casting enum dilakukan di sisi Laravel (`App\Enums\*`) sehingga aplikasi tetap type-safe.

## 8. Ringkasan Index

| Tabel | Kolom | Tipe | Alasan |
| --- | --- | --- | --- |
| `departments` | `nama_dept` | UNIQUE | Cegah duplikasi nama |
| `employees` | `nip` | UNIQUE | Identitas unik karyawan |
| `employees` | `nama` | B-TREE | Pencarian nama |
| `employees` | `department_id` | FK index | Filter per departemen |
| `assets` | `asset_code` | UNIQUE PARTIAL (`deleted_at IS NULL`) | Kode aset unik antar aset aktif |
| `assets` | `hostname` | UNIQUE PARTIAL (`deleted_at IS NULL AND hostname IS NOT NULL`) | Nama komputer unik antar aset aktif |
| `assets` | `mac_address` | UNIQUE PARTIAL (`deleted_at IS NULL`) | **Validasi duplikasi MAC** |
| `assets` | `ip_address` | UNIQUE PARTIAL (`deleted_at IS NULL AND ip_address IS NOT NULL`) | **Validasi duplikasi IP** |
| `assets` | `status`, `type` | B-TREE | Filter dashboard |
| `asset_assignments` | `(asset_id, returned_date)` | COMPOSITE | Query assignment aktif & riwayat |
| `asset_assignments` | `asset_id` WHERE `returned_date IS NULL` | UNIQUE PARTIAL | Satu assignment aktif per aset |
| `asset_assignments` | `(employee_id, returned_date)` | COMPOSITE | Hitung aset aktif per departemen (dashboard) |
| `asset_assignments` | `(assigned_date, id)` | COMPOSITE | Daftar alokasi terbaru (ORDER BY ... LIMIT) |
| `components` | `component_code` | UNIQUE PARTIAL (`deleted_at IS NULL`) | Kode komponen unik antar komponen aktif |
| `components` | `serial_number` | UNIQUE PARTIAL (`deleted_at IS NULL AND serial_number IS NOT NULL`) | Nomor seri unik bila diisi |
| `components` | `category`, `status` | B-TREE | Filter daftar komponen |
| `component_installations` | `(asset_id, removed_date)` | COMPOSITE | Komponen aktif pada sebuah host |
| `component_installations` | `(component_id, removed_date)` | COMPOSITE | Riwayat pemasangan sebuah komponen |
| `component_installations` | `component_id` WHERE `removed_date IS NULL` | UNIQUE PARTIAL | Satu komponen terpasang di satu host |

## 9. Urutan Migrasi

1. `2014_10_12_000000_create_users_table.php` (sudah ada)
2. `2026_01_01_000001_add_role_to_users_table.php`
3. `2026_01_01_000002_create_departments_table.php`
4. `2026_01_01_000003_create_employees_table.php`
5. `2026_01_01_000004_create_assets_table.php`
6. `2026_01_01_000005_create_asset_assignments_table.php`
7. `2026_01_01_000006_add_dashboard_indexes_to_asset_assignments_table.php`
8. `2026_01_01_000007_add_hostname_to_assets_table.php`

Fase 2 (belum diimplementasikan):

9. `xxxx_create_components_table.php`
10. `xxxx_create_component_installations_table.php`

## 10. Cast Model (Target)

```php
// Asset.php
protected $casts = [
    'specs'  => 'array',
    'type'   => AssetType::class,
    'status' => AssetStatus::class,
];

// Asset.php — soft delete
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes;
}

// AssetAssignment.php
protected $casts = [
    'assigned_date' => 'date',
    'returned_date' => 'date',
];
```

### Implikasi Soft Delete pada Validasi Unique

Karena `assets` memakai soft delete, aturan `Rule::unique()` pada Form Request **harus dibatasi ke baris yang belum dihapus**, agar konsisten dengan partial index:

```php
Rule::unique('assets', 'mac_address')->whereNull('deleted_at')
```

Jika tidak, validasi aplikasi akan menolak MAC yang sebenarnya sudah bebas karena aset lamanya telah dihapus.

### Kebijakan Restore

- Aset yang di-soft delete tetap muncul di laporan khusus (filter "Termasuk yang dihapus").
- Restore (`restore()`) harus memvalidasi ulang konflik `asset_code`, `mac_address`, dan `ip_address`: jika salah satunya sudah dipakai aset aktif lain, restore ditolak dengan pesan jelas. (`asset_code` wajib dicek karena unique index-nya juga parsial.)
- `asset_assignments` **tidak** memakai soft delete (append-only); barisnya ikut tetap ada karena FK `cascadeOnDelete` hanya terpicu oleh hard delete.

## 11. Relasi Tambahan

Selain relasi dasar, `Department` memiliki relasi `hasManyThrough` untuk menghitung alokasi aset per departemen (dipakai dashboard):

```php
// app/Models/Department.php
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
```

Dengan relasi ini, `withCount(['assignments as assigned_assets_count' => fn ($q) => $q->whereNull('returned_date')])` menghitung **baris assignment aktif** per departemen — bukan jumlah karyawan, yang bisa berbeda bila satu karyawan memegang lebih dari satu aset.
