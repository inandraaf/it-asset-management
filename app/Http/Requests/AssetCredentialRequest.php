<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi kredensial aset (S5).
 */
class AssetCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'min:2', 'max:100'],
            'username' => ['nullable', 'string', 'max:150'],
            'password' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullable = fn ($v) => ($v === null || trim((string) $v) === '') ? null : trim((string) $v);

        $this->merge([
            'label' => trim((string) $this->input('label')),
            'username' => $nullable($this->input('username')),
            'password' => $nullable($this->input('password')),
            'notes' => $nullable($this->input('notes')),
        ]);
    }

    public function messages(): array
    {
        return [
            'label.required' => 'Label kredensial wajib diisi.',
            'label.min' => 'Label minimal 2 karakter.',
            'label.max' => 'Label maksimal 100 karakter.',
        ];
    }
}
