<?php

namespace App\Http\Requests\Component;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request untuk operasi komponen massal (FB-6).
 *
 * Dipakai tiga aksi: pasang massal, lepas massal, pindah massal.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-6
 */
class BulkComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Daftar komponen yang dipilih (boleh komponen maupun installation).
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['integer'],

            'date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // Khusus pindah massal.
            'target_asset_id' => [
                Rule::requiredIf(fn () => $this->routeIs('components.bulk-move.store')),
                'nullable', 'integer', Rule::exists('assets', 'id'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes') && blank($this->input('notes'))) {
            $this->merge(['notes' => null]);
        }
    }

    /**
     * Daftar ID komponen yang dipilih.
     *
     * @return array<int, int>
     */
    public function componentIds(): array
    {
        return array_map('intval', (array) $this->input('items', []));
    }

    /**
     * Daftar ID installation (pemasangan) yang dipilih.
     *
     * @return array<int, int>
     */
    public function installationIds(): array
    {
        return $this->componentIds();
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal satu komponen.',
            'items.min' => 'Pilih minimal satu komponen.',
            'date.required' => 'Tanggal wajib diisi.',
            'date.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'target_asset_id.required' => 'Host tujuan wajib dipilih.',
            'target_asset_id.exists' => 'Host tidak ditemukan.',
        ];
    }
}
