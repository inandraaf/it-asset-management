<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Rules\MacAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi perubahan aset.
 *
 * `type`, `asset_code`, dan `hostname` tidak diikutkan:
 * - `type` dan `asset_code` immutable setelah aset dibuat.
 * - `hostname` dikelola lewat jalur terpisah (belum ada di MVP) agar tidak
 *   berubah tanpa sengaja saat admin memperbaiki spesifikasi.
 *
 * @see dokumentasi/10-validasi.md §6
 */
class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $assetId = $this->route('asset')->id;

        return [
            'brand' => ['nullable', 'string', 'max:100'],
            'mac_address' => $this->assetIsComputer()
                ? ['required', 'string', new MacAddress, Rule::unique('assets', 'mac_address')->ignore($assetId)->whereNull('deleted_at')]
                : ['nullable', 'string', new MacAddress, Rule::unique('assets', 'mac_address')->ignore($assetId)->whereNull('deleted_at')],
            'ip_address' => [
                'nullable', 'ip', 'max:45',
                Rule::unique('assets', 'ip_address')->ignore($assetId)->whereNull('deleted_at'),
            ],
            // 'specs' nullable: Fase 2 hanya menyimpan OS yang bersifat opsional.
            'specs' => ['nullable', 'array'],
            ...$this->specRules(),
            'status' => ['required', Rule::enum(AssetStatus::class)],
        ];
    }

    /**
     * @return array<string, array<int, string>>
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

    /**
     * Apakah aset yang sedang diedit berjenis komputer (FB-4).
     */
    protected function assetIsComputer(): bool
    {
        return $this->route('asset')?->type->isComputer() ?? true;
    }

    protected function prepareForValidation(): void
    {
        $normalize = fn (?string $v) => ($v === null || trim($v) === '') ? null : trim($v);

        $this->merge([
            'brand' => trim((string) $this->input('brand')) ?: null,
            'mac_address' => $this->filled('mac_address')
                ? strtoupper(trim((string) $this->input('mac_address')))
                : $this->input('mac_address'),
            'ip_address' => $normalize($this->input('ip_address')),
            'specs' => $this->normalizedSpecs(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function normalizedSpecs(): array
    {
        $input = (array) $this->input('specs', []);
        $clean = [];

        foreach (Asset::SPEC_KEYS as $key) {
            $value = $input[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $clean[$key] = trim($value);
            }
        }

        return $clean;
    }

    /**
     * Status aset yang sedang dipegang tidak boleh diubah manual menjadi
     * Available — harus lewat fitur Return agar riwayat tetap konsisten.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $asset = $this->route('asset');

            if ($asset->status === AssetStatus::Assigned
                && $this->input('status') === AssetStatus::Available->value) {
                $validator->errors()->add(
                    'status',
                    'Aset sedang dipegang karyawan. Gunakan fitur Return untuk mengembalikannya.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'brand.max' => 'Merek maksimal 100 karakter.',
            'mac_address.required' => 'MAC Address wajib diisi.',
            'mac_address.unique' => 'MAC Address sudah dipakai aset lain.',
            'ip_address.unique' => 'IP Address sudah dipakai aset lain.',
            'ip_address.ip' => 'Format IP Address tidak valid.',
            'specs.cpu.required' => 'Spesifikasi CPU wajib diisi.',
            'status.required' => 'Status wajib dipilih.',
        ];
    }
}
