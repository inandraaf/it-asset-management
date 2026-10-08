<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Edit Departemen') }}</h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ $department->nama_dept }}
        </p>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <form method="POST" action="{{ route('departments.update', $department) }}" autocomplete="off" class="space-y-6">
            @csrf
            @method('put')

            <x-card :title="__('Data Departemen')">
                <div>
                    <x-input-label for="nama_dept" :value="__('Nama Departemen')" />
                    <x-text-input id="nama_dept" name="nama_dept" type="text" class="mt-1"
                    :value="old('nama_dept', $department->nama_dept)" required autofocus maxlength="100" />
                    <x-input-error class="mt-2" :messages="$errors->get('nama_dept')" />
                    </div>
                </x-card>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('departments.index') }}">
                        <x-secondary-button>{{ __('Batal') }}</x-secondary-button>
                    </a>
                    <x-primary-button>{{ __('Perbarui Departemen') }}</x-primary-button>
                </div>
            </form>
        </div>
    </x-app-layout>
