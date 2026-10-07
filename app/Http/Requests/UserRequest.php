<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validasi manajemen user (dikelola Admin IT).
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md S3
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $isUpdate = $user !== null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                // alpha_dash tidak mengizinkan titik; username internal lazim memakai titik.
                'required', 'string', 'max:50', 'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user?->id),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            // Saat update, kata sandi boleh dikosongkan (tidak diubah).
            'password' => [
                $isUpdate ? 'nullable' : 'required',
                'confirmed',
                Password::defaults(),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'username' => strtolower(trim((string) $this->input('username'))),
        ]);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'Nama pengguna wajib diisi.',
            'username.unique' => 'Nama pengguna sudah dipakai.',
            'username.regex' => 'Nama pengguna hanya boleh huruf kecil, angka, titik, tanda hubung, dan garis bawah.',
            'role.required' => 'Peran wajib dipilih.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ];
    }
}
