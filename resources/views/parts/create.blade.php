<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Tambah Komponen') }}</h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Catat part komputer baru') }}
        </p>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6"
         x-data="{ category: @js(old('category', 'ram')) }">
        <form method="POST" action="{{ route('components.store') }}" autocomplete="off" class="space-y-6">
            @csrf

            <x-card :title="__('Identitas Komponen')">
                <div class="space-y-6">
                    <div>
                        <x-input-label for="category" :value="__('Kategori')" />
                        <select id="category" name="category" required x-model="category"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (\App\Enums\ComponentCategory::groupedOptions() as $group => $options)
                                <optgroup label="{{ $group }}">
                                    @foreach ($options as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('category')" />
                    </div>

                    <div class="rounded-lg bg-slate-50 px-4 py-3">
                        <p class="flex items-start gap-2 text-xs text-slate-600">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                            <span>{{ __('Kode komponen dibuat otomatis oleh sistem sesuai kategori (contoh: RAM-2026-0001).') }}</span>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="brand" :value="__('Merek')" />
                            <x-text-input id="brand" name="brand" type="text" class="mt-1"
                                          :value="old('brand')" required maxlength="100" placeholder="Kingston, Samsung..." />
                            <x-input-error class="mt-2" :messages="$errors->get('brand')" />
                        </div>

                        <div>
                            <x-input-label for="model" :value="__('Model (opsional)')" />
                            <x-text-input id="model" name="model" type="text" class="mt-1"
                                          :value="old('model')" maxlength="150" placeholder="Fury Beast DDR4" />
                            <x-input-error class="mt-2" :messages="$errors->get('model')" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="serial_number" :value="__('Nomor Seri (opsional)')" />
                            <x-text-input id="serial_number" name="serial_number" type="text" class="mt-1 font-mono"
                                          :value="old('serial_number')" maxlength="100" />
                            <x-input-error class="mt-2" :messages="$errors->get('serial_number')" />
                        </div>
                    </div>
                </div>
            </x-card>

            <x-card :title="__('Spesifikasi')"
                    :description="__('Cukup isi yang penting; field lanjutan opsional.')">
                {{-- Tiap kategori dibungkus <fieldset> yang di-DISABLE saat tidak aktif.
                     Beberapa kategori memakai key `specs` yang sama (RAM & Storage
                     sama-sama punya `capacity`/`type`). Tanpa disable, browser mengirim
                     DUA nilai untuk `specs[capacity]` dan PHP mengambil yang terakhir
                     (milik kategori yang sedang disembunyikan), sehingga spesifikasi
                     kategori aktif hilang. --}}
                @foreach ($specMap as $categoryValue => $meta)
                    <fieldset x-show="category === @js($categoryValue)" x-cloak
                              x-bind:disabled="category !== @js($categoryValue)"
                              class="m-0 min-w-0 border-0 p-0">
                        @if (empty($meta['keys']) && empty($meta['advanced']))
                            <p class="text-sm text-slate-500">{{ __('Tidak ada field spesifikasi khusus untuk kategori ini.') }}</p>
                        @else
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                @foreach ($meta['keys'] as $key => $field)
                                    <div>
                                        <x-input-label :for="'spec_'.$key" :value="$field['label']" />
                                        <x-spec-field :key="$key" :field="$field" />
                                        <x-input-error class="mt-2" :messages="$errors->get('specs.'.$key)" />
                                    </div>
                                @endforeach
                            </div>

                            @if (! empty($meta['advanced']))
                                <details class="mt-5 rounded-lg border border-slate-200">
                                    <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50">
                                        {{ __('Spesifikasi lanjutan (opsional)') }}
                                    </summary>

                                    <div class="grid grid-cols-1 gap-6 border-t border-slate-200 p-4 sm:grid-cols-2">
                                        @foreach ($meta['advanced'] as $key => $field)
                                            <div>
                                                <x-input-label :for="'spec_'.$key" :value="$field['label']" />
                                                <x-spec-field :key="$key" :field="$field" />
                                                <x-input-error class="mt-2" :messages="$errors->get('specs.'.$key)" />
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        @endif
                    </fieldset>
                @endforeach
            </x-card>

            <x-card :title="__('Catatan')">
                <div>
                    <x-input-label for="notes" :value="__('Catatan (opsional)')" />
                    <textarea id="notes" name="notes" rows="3" maxlength="1000"
                              class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="{{ __('Kondisi, kelengkapan, dsb.') }}">{{ old('notes') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('components.index') }}">
                    <x-secondary-button>{{ __('Batal') }}</x-secondary-button>
                </a>
                <x-primary-button>{{ __('Simpan Komponen') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
