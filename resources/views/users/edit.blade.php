<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Edit Akun') }}</h1>
            <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">{{ $user->username }}</span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <form method="POST" action="{{ route('users.update', $user) }}" autocomplete="off" class="space-y-6">
            @csrf
            @method('put')

            <x-card :title="__('Data Akun')">
                <div class="space-y-6">
                    <div>
                        <x-input-label for="name" :value="__('Nama Lengkap')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1"
                                      :value="old('name', $user->name)" required autofocus maxlength="255" />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="username" :value="__('Nama Pengguna')" />
                        <x-text-input id="username" name="username" type="text" class="mt-1 font-mono"
                                      :value="old('username', $user->username)" required maxlength="50" />
                        <x-input-error class="mt-2" :messages="$errors->get('username')" />
                    </div>

                    <div>
                        <x-input-label for="role" :value="__('Peran')" />
                        <select id="role" name="role" required
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($roles as $value => $label)
                                <option value="{{ $value }}" @selected(old('role', $user->role->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('role')" />
                    </div>
                </div>
            </x-card>

            <x-card :title="__('Kata Sandi')"
                    :description="__('Kosongkan bila tidak ingin mengubah kata sandi.')">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="password" :value="__('Kata Sandi Baru')" />
                        <x-text-input id="password" name="password" type="password" class="mt-1" autocomplete="new-password" />
                        <x-input-error class="mt-2" :messages="$errors->get('password')" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" :value="__('Ulangi Kata Sandi')" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1" autocomplete="new-password" />
                    </div>
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('users.index') }}">
                    <x-secondary-button>{{ __('Batal') }}</x-secondary-button>
                </a>
                <x-primary-button>{{ __('Perbarui Akun') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
