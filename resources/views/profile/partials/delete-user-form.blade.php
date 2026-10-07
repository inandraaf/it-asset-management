<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-slate-900">
            {{ __('Hapus Akun') }}
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            {{ __('Setelah akun dihapus, seluruh data yang terkait akan hilang permanen. Pastikan Anda sudah menyimpan informasi yang diperlukan sebelum melanjutkan.') }}
        </p>
    </header>

    <x-danger-button
    x-data=""
    x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Hapus Akun') }}</x-danger-button>

<x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
    <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
        @csrf
        @method('delete')

        <h2 class="text-lg font-medium text-slate-900">
            {{ __('Yakin ingin menghapus akun ini?') }}
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            {{ __('Setelah akun dihapus, seluruh data terkait akan hilang permanen. Masukkan kata sandi Anda untuk mengonfirmasi penghapusan akun.') }}
        </p>

        <div class="mt-6">
            <x-input-label for="password" value="{{ __('Kata Sandi') }}" class="sr-only" />

            <x-text-input
            id="password"
            name="password"
            type="password"
            class="mt-1 block w-3/4"
            placeholder="{{ __('Kata Sandi') }}"
            />

            <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Batal') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Hapus Akun') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
