<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Tambah Karyawan') }}</h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Daftarkan karyawan baru beserta departemennya') }}
        </p>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <form method="POST" action="{{ route('employees.store') }}" class="space-y-6">
            @csrf

            <x-card :title="__('Data Karyawan')">
                <div class="space-y-6">
                    <div>
                        <x-input-label for="nip" :value="__('NIP')" />
                        <x-text-input id="nip" name="nip" type="text" class="mt-1 font-mono"
                        :value="old('nip')" required autofocus maxlength="30" placeholder="20260001" />
                        <x-input-error class="mt-2" :messages="$errors->get('nip')" />
                        </div>

                        <div>
                            <x-input-label for="nama" :value="__('Nama')" />
                            <x-text-input id="nama" name="nama" type="text" class="mt-1"
                            :value="old('nama')" required maxlength="150" />
                            <x-input-error class="mt-2" :messages="$errors->get('nama')" />
                            </div>

                            <div>
                                <x-input-label for="department_id" :value="__('Departemen')" />
                                <select id="department_id" name="department_id" required
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('-- Pilih Departemen --') }}</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>
                                        {{ $department->nama_dept }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('department_id')" />
                            </div>
                        </div>
                    </x-card>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('employees.index') }}">
                            <x-secondary-button>{{ __('Batal') }}</x-secondary-button>
                        </a>
                        <x-primary-button>{{ __('Simpan Karyawan') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </x-app-layout>
