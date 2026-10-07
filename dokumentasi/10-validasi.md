# 10 — Aturan Validasi (Form Request)

Dokumen ini adalah **acuan tunggal** aturan validasi. Jangan menduplikasi logika ini di controller atau view.

## 1. Prinsip

1. Semua validasi memakai Form Request (`app/Http/Requests`).
2. Gunakan `Rule::unique()->ignore()` untuk kasus update.
3. Pesan error berbahasa Indonesia, eksplisit menyebut solusi.
4. Normalisasi input di `prepareForValidation()`: `trim`, uppercase MAC, ubah string kosong menjadi `null` untuk `ip_address`.
5. Validasi aplikasi **dan** constraint database adalah lapisan berbeda — keduanya wajib (jaga integritas walau ada akses langsung ke DB).

## 2. `DepartmentRequest`

```php
public function rules(): array
{
    return [
        'nama_dept' => ['required', 'string', 'min:2', 'max:100'],
    ];
}

protected function prepareForValidation(): void
{
    $this->merge(['nama_dept' => trim((string) $this->nama_dept)]);
}

/**
 * Keunikan case-insensitive ("RnD" == "rnd").
 *
 * Rule `unique` bawaan TIDAK BISA dipakai: ia selalu menambahkan
 * where(nama_dept = <nilai>) secara persis (case-sensitive) lalu
 * di-AND dengan kondisi tambahan, sehingga "rnd" tidak akan pernah
 * menemukan baris "RnD".
 */
public function withValidator(Validator $validator): void
{
    $validator->after(function (Validator $validator) {
        if ($validator->errors()->has('nama_dept')) {
            return;
        }

        $query = Department::query()
            ->whereRaw('LOWER(nama_dept) = ?', [mb_strtolower((string) $this->input('nama_dept'))]);

        if ($current = $this->route('department')) {
            $query->where('id', '!=', $current->id);
        }

        if ($query->exists()) {
            $validator->errors()->add('nama_dept', 'Nama departemen sudah digunakan.');
        }
    });
}

public function messages(): array
{
    return [
        'nama_dept.required' => 'Nama departemen wajib diisi.',
        'nama_dept.min'      => 'Nama departemen minimal 2 karakter.',
        'nama_dept.max'      => 'Nama departemen maksimal 100 karakter.',
    ];
}
```

## 3. `EmployeeRequest`

```php
public function rules(): array
{
    $id = $this->route('employee')?->id;

    return [
        'nip'           => ['required', 'string', 'max:30',
            Rule::unique('employees', 'nip')->ignore($id)],
        'nama'          => ['required', 'string', 'min:2', 'max:150'],
        'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
    ];
}

protected function prepareForValidation(): void
{
    $this->merge([
        'nip'  => trim((string) $this->nip),
        'nama' => trim((string) $this->nama),
    ]);
}

public function messages(): array
{
    return [
        'nip.required'           => 'NIP wajib diisi.',
        'nip.unique'             => 'NIP sudah terdaftar.',
        'nama.required'          => 'Nama karyawan wajib diisi.',
        'department_id.required' => 'Departemen wajib dipilih.',
        'department_id.exists'   => 'Departemen tidak ditemukan.',
    ];
}
```

## 4. Validasi MAC Address

### Format

Regex yang diterima (case-insensitive pada input, dinormalisasi ke uppercase):

```
/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/
```

Menerima format: `AA:BB:CC:DD:EE:FF`.
**Tidak** menerima format titik (`AABB.CCDD.EEFF`) atau tanpa pemisah.

### Custom Rule

```php
// app/Rules/MacAddress.php
class MacAddress implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', strtoupper($value))) {
            $fail('Format MAC Address harus AA:BB:CC:DD:EE:FF.');
            return;
        }

        if (in_array(strtoupper($value), ['00:00:00:00:00:00', 'FF:FF:FF:FF:FF:FF'], true)) {
            $fail('MAC Address tersebut tidak valid untuk perangkat.');
        }
    }
}
```

### Normalisasi

```php
protected function prepareForValidation(): void
{
    if ($this->filled('mac_address')) {
        $this->merge(['mac_address' => strtoupper(trim((string) $this->mac_address))]);
    }
    if ($this->has('ip_address') && blank($this->ip_address)) {
        $this->merge(['ip_address' => null]);
    }
}
```

## 5. `StoreAssetRequest`

```php
public function rules(): array
{
    return [
        // 'asset_code' TIDAK ada di sini — selalu dibuat sistem.
        'type'        => ['required', Rule::enum(AssetType::class)],
        'brand'       => ['required', 'string', 'min:2', 'max:100'],
        'hostname'    => ['nullable', 'string', 'max:63',
            'regex:/^[A-Za-z0-9][A-Za-z0-9.\-]*$/',
            Rule::unique('assets', 'hostname')->whereNull('deleted_at')],
        'mac_address' => ['required', 'string', new MacAddress(),
            Rule::unique('assets', 'mac_address')->whereNull('deleted_at')],
        'ip_address'  => ['nullable', 'ip', 'max:45',
            Rule::unique('assets', 'ip_address')->whereNull('deleted_at')],
        'specs'       => ['required', 'array'],
        ...$this->specRules(),          // diturunkan dari Asset::SPEC_KEYS
        'status'      => ['sometimes', Rule::enum(AssetStatus::class)],
    ];
}

/**
 * Aturan per komponen, diturunkan dari konstanta model sehingga menambah
 * komponen cukup mengubah Asset::SPEC_KEYS — tidak ada daftar ganda.
 */
private function specRules(): array
{
    $rules = [];
    foreach (Asset::SPEC_KEYS as $key) {
        $rules["specs.$key"] = in_array($key, Asset::REQUIRED_SPEC_KEYS, true)
            ? ['required', 'string', 'max:100']
            : ['nullable', 'string', 'max:100'];
    }
    return $rules;
}

public function messages(): array
{
    return [
        'type.required'            => 'Jenis aset wajib dipilih.',
        'brand.required'           => 'Merek wajib diisi.',
        'hostname.regex'           => 'Nama komputer hanya boleh huruf, angka, tanda hubung, dan titik.',
        'hostname.unique'          => 'Nama komputer sudah dipakai aset lain.',
        'mac_address.required'     => 'MAC Address wajib diisi.',
        'mac_address.unique'       => 'MAC Address sudah dipakai aset lain.',
        'ip_address.unique'        => 'IP Address sudah dipakai aset lain.',
        'ip_address.ip'            => 'Format IP Address tidak valid.',
        'specs.cpu.required'       => 'Spesifikasi CPU wajib diisi.',
    ];
}
```

> Validasi `ip` bawaan Laravel menerima IPv4 dan IPv6.
>
> **Hostname** dinormalisasi menjadi lowercase di `prepareForValidation()`, sehingga
> `PC-RND-01` dan `pc-rnd-01` dianggap sama. Key spesifikasi yang kosong dibuang
> sebelum validasi agar JSON tersimpan ringkas.

## 6. `UpdateAssetRequest`

Perbedaan dari `StoreAssetRequest`:

```php
public function rules(): array
{
    $asset = $this->route('asset');

    return [
        'brand'       => ['required', 'string', 'min:2', 'max:100'],
        'mac_address' => ['required', 'string', new MacAddress(),
            Rule::unique('assets', 'mac_address')->ignore($asset->id)->whereNull('deleted_at')],
        'ip_address'  => ['nullable', 'ip', 'max:45',
            Rule::unique('assets', 'ip_address')->ignore($asset->id)->whereNull('deleted_at')],
        'specs'       => ['required', 'array'],
        ...$this->specRules(),      // sama seperti StoreAssetRequest
        'status'      => ['required', Rule::enum(AssetStatus::class)],
        // 'type', 'asset_code', dan 'hostname' sengaja tidak diikutkan
        // → tidak bisa diubah lewat form edit
    ];
}
```

Aturan tambahan di `withValidator()`:

```php
public function withValidator($validator): void
{
    $validator->after(function ($v) {
        $asset = $this->route('asset');

        if ($asset->status === AssetStatus::Assigned
            && $this->input('status') === AssetStatus::Available->value) {
            $v->errors()->add('status',
                'Aset sedang dipegang karyawan. Gunakan fitur Return untuk mengembalikannya.');
        }
    });
}
```

## 7. `AssignAssetRequest`

```php
public function rules(): array
{
    return [
        'employee_id'   => ['required', 'integer', Rule::exists('employees', 'id')],
        'assigned_date' => ['required', 'date', 'before_or_equal:today'],
        'notes'         => ['nullable', 'string', 'max:1000'],
    ];
}

public function messages(): array
{
    return [
        'employee_id.required'      => 'Karyawan wajib dipilih.',
        'employee_id.exists'        => 'Karyawan tidak ditemukan.',
        'assigned_date.required'    => 'Tanggal assign wajib diisi.',
        'assigned_date.before_or_equal' => 'Tanggal assign tidak boleh di masa depan.',
    ];
}
```

Validasi status aset `Available` dilakukan di `AssetAllocationService` (butuh row lock), bukan di Form Request.

## 8. `ReturnAssetRequest`

```php
public function rules(): array
{
    return [
        'returned_date' => ['required', 'date', 'before_or_equal:today'],
        'notes'         => ['nullable', 'string', 'max:1000'],
    ];
}
```

Validasi `returned_date >= assigned_date` di service (butuh data assignment aktif).

## 8b. `TransferAssetRequest`

```php
public function rules(): array
{
    return [
        'employee_id'  => ['required', 'integer', Rule::exists('employees', 'id')],
        'transfer_date' => ['required', 'date', 'before_or_equal:today'],
        'notes'        => ['nullable', 'string', 'max:1000'],
    ];
}

public function messages(): array
{
    return [
        'employee_id.required'      => 'Karyawan tujuan wajib dipilih.',
        'employee_id.exists'        => 'Karyawan tidak ditemukan.',
        'transfer_date.required'    => 'Tanggal transfer wajib diisi.',
        'transfer_date.before_or_equal' => 'Tanggal transfer tidak boleh di masa depan.',
    ];
}
```

Aturan "karyawan tujuan ≠ pemegang saat ini" dan "aset harus berstatus `Assigned`" divalidasi di `AssetAllocationService` karena membutuhkan data assignment aktif.

## 9. Matriks Ringkas

| Field | required | unique | format | catatan |
| --- | --- | --- | --- | --- |
| `nama_dept` | ✅ | ✅ | 2–100 char | trim |
| `nip` | ✅ | ✅ | ≤30 char | trim |
| `nama` | ✅ | ❌ | 2–150 char | trim |
| `department_id` | ✅ | ❌ | exists | |
| `asset_code` | — | — | — | **Tidak divalidasi dari form**; selalu dibuat sistem |
| `type` | ✅ | ❌ | enum PC/Laptop | immutable saat update |
| `brand` | ✅ | ❌ | 2–100 char | |
| `hostname` | ❌ | ✅ bila diisi | ≤63 char, `^[A-Za-z0-9][A-Za-z0-9.\-]*$` | lowercase; unique antar baris aktif |
| `mac_address` | **kondisional** | ✅ | `^([0-9A-F]{2}:){5}[0-9A-F]{2}$` | Wajib PC/Laptop; opsional CCTV; tidak dipakai Printer |
| `department_id` | **kondisional** | ❌ | exists | Wajib untuk CCTV/Printer |
| `ip_address` | ❌ | ✅ bila diisi | `ip` IPv4/IPv6 | blank → null; unique antar baris aktif |
| `specs.cpu` | ✅ | ❌ | ≤100 char | komponen wajib |
| `specs.*` (11 lainnya) | ❌ | ❌ | ≤100 char | kosong tidak disimpan |
| `status` | ✅ (update) | ❌ | enum 4 nilai | tidak boleh Assigned → Available manual |
| `assigned_date` | ✅ | ❌ | ≤ hari ini | |
| `returned_date` | ✅ | ❌ | ≤ hari ini, ≥ `assigned_date` | |
| `transfer.employee_id` | ✅ | ❌ | exists, ≠ pemegang saat ini | hanya untuk aset `Assigned` |

## 10. Uji Validasi yang Wajib Ada

- [ ] MAC duplikat ditolak (`StoreAssetRequest`, `UpdateAssetRequest`).
- [ ] IP duplikat ditolak.
- [ ] Dua aset dengan IP `null` berhasil disimpan.
- [ ] MAC format salah (`AABBCCDDEEFF`, `AA-BB-...`, `GG:...`) ditolak.
- [ ] MAC lowercase `aa:bb:cc:dd:ee:ff` diterima dan tersimpan uppercase.
- [ ] `nip` duplikat ditolak; update dengan `nip` yang sama pada record yang sama berhasil.
- [ ] `department_id` yang tidak ada ditolak.
- [ ] Update aset mengosongkan `type` tidak berpengaruh (field diabaikan).
- [ ] MAC/IP milik aset yang sudah di-soft delete **boleh** dipakai ulang.
- [ ] MAC/IP milik aset aktif tetap ditolak meskipun ada aset terhapus dengan nilai berbeda.
- [ ] Transfer ke pemegang saat ini ditolak.
