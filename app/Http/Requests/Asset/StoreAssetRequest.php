<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Rules\MacAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi input aset baru.
 *
 * Kode aset TIDAK diterima dari form: selalu dibuat sistem lewat
 * CodeGenerator agar tidak ada tabrakan atau human error.
 *
 * @see dokumentasi/10-validasi.md §5
 */
class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AssetType::class)],
            'brand' => ['required', 'string', 'min:2', 'max:100'],
            'hostname' => [
                'nullable', 'string', 'max:63',
                // Format nama host: huruf/angka, boleh tanda hubung dan titik.
                'regex:/^[A-Za-z0-9][A-Za-z0-9.\-]*$/',
                Rule::unique('assets', 'hostname')->whereNull('deleted_at'),
            ],
            'mac_address' => [
                'required', 'string', new MacAddress,
                Rule::unique('assets', 'mac_address')->whereNull('deleted_at'),
            ],
            'ip_address' => [
                'nullable', 'ip', 'max:45',
                Rule::unique('assets', 'ip_address')->whereNull('deleted_at'),
            ],
            // 'specs' nullable: Fase 2 hanya menyimpan OS yang bersifat opsional.
            'specs' => ['nullable', 'array'],
            ...$this->specRules(),
            'status' => ['sometimes', Rule::enum(AssetStatus::class)],
        ];
    }

    /**
     * Aturan per key spesifikasi, diturunkan dari konstanta model sehingga
     * menambah komponen cukup mengubah Asset::SPEC_KEYS.
     *
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

    protected function prepareForValidation(): void
    {
        $normalize = fn (?string $v) => ($v === null || trim($v) === '') ? null : trim($v);

        $this->merge([
            'brand' => trim((string) $this->input('brand')),
            'hostname' => $normalize($this->input('hostname')) === null
                ? null
                : strtolower($normalize($this->input('hostname'))),
            'mac_address' => $this->filled('mac_address')
                ? strtoupper(trim((string) $this->input('mac_address')))
                : $this->input('mac_address'),
            'ip_address' => $normalize($this->input('ip_address')),
            'specs' => $this->normalizedSpecs(),
        ]);
    }

    /**
     * Buang key spesifikasi yang kosong agar JSON tetap ringkas.
     *
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

    public function messages(): array
    {
        return [
            'type.required' => 'Jenis aset wajib dipilih.',
            'brand.required' => 'Merek wajib diisi.',
            'hostname.regex' => 'Nama komputer hanya boleh huruf, angka, tanda hubung, dan titik.',
            'hostname.unique' => 'Nama komputer sudah dipakai aset lain.',
            'mac_address.required' => 'MAC Address wajib diisi.',
            'mac_address.unique' => 'MAC Address sudah dipakai aset lain.',
            'ip_address.unique' => 'IP Address sudah dipakai aset lain.',
            'ip_address.ip' => 'Format IP Address tidak valid.',
            'specs.cpu.required' => 'Spesifikasi CPU wajib diisi.',
        ];
    }
}
