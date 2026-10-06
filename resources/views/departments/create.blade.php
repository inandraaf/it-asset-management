<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Tambah Departemen') }}</h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Buat departemen baru') }}
        </p>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <form method="POST" action="{{ route('departments.store') }}" class="space-y-6">
            @csrf

            <x-card :title="__('Data Departemen')">
                <div>
                    <x-input-label for="nama_dept" :value="__('Nama Departemen')" />
                    <x-text-input id="nama_dept" name="nama_dept" type="text" class="mt-1"
                    :value="old('nama_dept')" required autofocus maxlength="100" placeholder="Contoh: RnD" />
                    <x-input-error class="mt-2" :messages="$errors->get('nama_dept')" />
                    </div>
                </x-card>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('departments.index') }}">
                        <x-secondary-button>{{ __('Batal') }}</x-secondary-button>
                    </a>
                    <x-primary-button>{{ __('Simpan Departemen') }}</x-primary-button>
                </div>
            </form>
        </div>
    </x-app-layout>
