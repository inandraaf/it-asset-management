<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Tambah Aset') }}</h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Daftarkan aset baru (komputer atau perangkat departemen)') }}
        </p>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6"
         x-data="{
            type: @js(old('type', 'PC')),
            mac: @js(old('mac_address')),
            formatMac(value) {
                const hex = value.toUpperCase().replace(/[^0-9A-F]/g, '').slice(0, 12);
                return hex.replace(/(.{2})(?=.)/g, '$1:');
            }
         }">
        <form method="POST" action="{{ route('assets.store') }}" class="space-y-6">
            @csrf

            <x-card :title="__('Identitas Aset')">
                <div class="space-y-6">
                    <div>
                        <x-input-label :value="__('Jenis Aset')" />
                        <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach ($types as $value => $label)
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-300 p-3 transition hover:bg-slate-50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                                    <input type="radio" name="type" value="{{ $value }}" required x-model="type"
                                           class="border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('type')" />
                    </div>

                    <div class="rounded-lg bg-slate-50 px-4 py-3">
                        <p class="flex items-start gap-2 text-xs text-slate-600">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                            <span>{{ __('Kode aset dibuat otomatis oleh sistem saat disimpan (contoh: PC-2026-0001), jadi tidak perlu diisi.') }}</span>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            {{-- PC opsional (banyak rakitan); Laptop/CCTV/Printer wajib. --}}
                            <x-input-label for="brand" :value="__('Merek & Model')" />
                            <x-text-input id="brand" name="brand" type="text" class="mt-1"
                                          :value="old('brand')" maxlength="100"
                                          x-bind:required="type !== 'PC'"
                                          placeholder="{{ __('Dell OptiPlex 7090 / Rakitan') }}" />
                            <p class="mt-1 text-xs text-slate-500" x-show="type === 'PC'">{{ __('Opsional untuk PC rakitan.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('brand')" />
                        </div>

                        <div>
                            <x-input-label for="hostname" :value="__('Nama Perangkat (hostname)')" />
                            <x-text-input id="hostname" name="hostname" type="text" class="mt-1 font-mono"
                                          :value="old('hostname')" maxlength="63" placeholder="pc-rnd-01" />
                            <p class="mt-1 text-xs text-slate-500">{{ __('Nama perangkat di jaringan/Windows.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('hostname')" />
                        </div>

                        {{-- Departemen: wajib untuk CCTV/Printer --}}
                        <div x-show="! ['PC', 'Laptop'].includes(type)" x-cloak>
                            <x-input-label for="department_id" :value="__('Departemen Pemilik')" />
                            <select id="department_id" name="department_id"
                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('-- Pilih Departemen --') }}</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>
                                        {{ $department->nama_dept }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">{{ __('Aset ini melekat pada departemen tersebut.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('department_id')" />
                        </div>
                    </div>
                </div>
            </x-card>

            <x-card :title="__('Jaringan')">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div x-show="type !== 'Printer'" x-cloak>
                        <x-input-label for="mac_address" :value="__('MAC Address')" />
                        <x-text-input id="mac_address" name="mac_address" type="text" class="mt-1 font-mono"
                                      :value="old('mac_address')"
                                      x-bind:required="['PC', 'Laptop'].includes(type)"
                                      maxlength="17" placeholder="AA:BB:CC:DD:EE:FF"
                                      x-model="mac"
                                      x-on:input="mac = formatMac($event.target.value)" />
                        <p class="mt-1 text-xs text-slate-500">
                            {{ __('Wajib untuk PC/Laptop; opsional untuk CCTV.') }}
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('mac_address')" />
                    </div>

                    <div>
                        <x-input-label for="ip_address" :value="__('IP Address (opsional)')" />
                        <x-text-input id="ip_address" name="ip_address" type="text" class="mt-1 font-mono"
                                      :value="old('ip_address')" maxlength="45" placeholder="192.168.1.10" />
                        <x-input-error class="mt-2" :messages="$errors->get('ip_address')" />
                    </div>
                </div>
            </x-card>

            <x-card :title="__('Sistem Operasi')"
                    x-show="type === 'PC' || type === 'Laptop'" x-cloak
                    :description="__('Komponen fisik (CPU, RAM, disk, dst.) ditambahkan dari halaman detail aset setelah aset dibuat.')">
                <div>
                    <x-input-label for="specs_os" :value="__('Sistem Operasi (opsional)')" />
                    <x-text-input id="specs_os" name="specs[os]" type="text" class="mt-1"
                                  :value="old('specs.os')" maxlength="100"
                                  placeholder="{{ __('Contoh: Windows 11 Pro 64-bit') }}" />
                    <x-input-error class="mt-2" :messages="$errors->get('specs.os')" />
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('assets.index') }}">
                    <x-secondary-button>{{ __('Batal') }}</x-secondary-button>
                </a>
                <x-primary-button>{{ __('Simpan Aset') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
