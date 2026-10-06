<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Tambah Aset') }}</h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Daftarkan PC atau Laptop baru') }}
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
                        <div class="mt-2 grid grid-cols-2 gap-3">
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
                            <x-input-label for="brand" :value="__('Merek & Model')" />
                            <x-text-input id="brand" name="brand" type="text" class="mt-1"
                                          :value="old('brand')" required maxlength="100" placeholder="Dell OptiPlex 7090" />
                            <x-input-error class="mt-2" :messages="$errors->get('brand')" />
                        </div>

                        <div>
                            <x-input-label for="hostname" :value="__('Nama Komputer (hostname)')" />
                            <x-text-input id="hostname" name="hostname" type="text" class="mt-1 font-mono"
                                          :value="old('hostname')" maxlength="63" placeholder="PC-RND-01" />
                            <p class="mt-1 text-xs text-slate-500">{{ __('Nama komputer di jaringan/Windows.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('hostname')" />
                        </div>
                    </div>
                </div>
            </x-card>

            <x-card :title="__('Jaringan')">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="mac_address" :value="__('MAC Address')" />
                        <x-text-input id="mac_address" name="mac_address" type="text" class="mt-1 font-mono"
                                      :value="old('mac_address')" required maxlength="17"
                                      placeholder="AA:BB:CC:DD:EE:FF"
                                      x-model="mac"
                                      x-on:input="mac = formatMac($event.target.value)" />
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

            <x-card :title="__('Spesifikasi Komponen')"
                    :description="__('Isi yang relevan saja; kosongkan yang tidak ada.')">
                @php
                    $placeholders = [
                        'cpu' => 'Contoh: Intel Core i5-10400',
                        'ram' => 'Contoh: 16GB (2x8GB) DDR4',
                        'storage' => 'Contoh: 512GB NVMe SSD',
                        'storage_2' => 'Contoh: 1TB HDD',
                        'gpu' => 'Contoh: NVIDIA GTX 1650 4GB',
                        'motherboard' => 'Contoh: ASUS H510M-E',
                        'psu' => 'Contoh: 500W 80+ Bronze',
                        'casing' => 'Contoh: ATX Mid Tower',
                        'os' => 'Contoh: Windows 11 Pro 64-bit',
                        'monitor' => 'Contoh: Dell P2219H 22"',
                        'keyboard' => 'Contoh: Logitech K120',
                        'mouse' => 'Contoh: Logitech B100',
                    ];
                @endphp

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    @foreach ($specKeys as $key)
                        @php($required = in_array($key, $requiredSpecKeys, true))
                        <div @class(['sm:col-span-2' => $key === 'cpu'])>
                            <x-input-label :for="'specs_'.$key" :value="$specLabels[$key].($required ? ' *' : '')" />
                            <x-text-input :id="'specs_'.$key" :name="'specs['.$key.']'" type="text" class="mt-1"
                                          :value="old('specs.'.$key)"
                                          :required="$required" maxlength="100"
                                          :placeholder="$placeholders[$key] ?? ''" />
                            <x-input-error class="mt-2" :messages="$errors->get('specs.'.$key)" />
                        </div>
                    @endforeach
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
