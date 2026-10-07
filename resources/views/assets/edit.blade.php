<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Edit Aset') }}</h1>
            <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">
                {{ $asset->asset_code }}
            </span>
        </div>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Perbarui merek, jaringan, spesifikasi, atau status') }}
        </p>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6"
         x-data="{
            mac: @js(old('mac_address', $asset->mac_address)),
            formatMac(value) {
                const hex = value.toUpperCase().replace(/[^0-9A-F]/g, '').slice(0, 12);
                return hex.replace(/(.{2})(?=.)/g, '$1:');
            }
         }">
        <form method="POST" action="{{ route('assets.update', $asset) }}" class="space-y-6">
            @csrf
            @method('put')

            <x-card :title="__('Identitas Aset')">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label :value="__('Kode Aset')" />
                        <p class="mt-1 font-mono text-sm text-slate-900">{{ $asset->asset_code }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Dibuat sistem, tidak dapat diubah.') }}</p>
                    </div>

                    <div>
                        <x-input-label :value="__('Jenis')" />
                        <p class="mt-1 text-sm text-slate-900">{{ $asset->type->label() }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Tidak dapat diubah.') }}</p>
                    </div>

                    <div>
                        <x-input-label :value="__('Nama Komputer (hostname)')" />
                        <p class="mt-1 font-mono text-sm text-slate-900">{{ $asset->hostname ?? '—' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Belum dapat diubah dari halaman ini.') }}</p>
                    </div>

                    <div>
                        <x-input-label for="brand" :value="__('Merek & Model (opsional)')" />
                        <x-text-input id="brand" name="brand" type="text" class="mt-1"
                                      :value="old('brand', $asset->brand)" maxlength="100" placeholder="Kosongkan bila rakitan" />
                        <x-input-error class="mt-2" :messages="$errors->get('brand')" />
                    </div>
                </div>
            </x-card>

            <x-card :title="__('Jaringan')">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="mac_address" :value="__('MAC Address')" />
                        <x-text-input id="mac_address" name="mac_address" type="text" class="mt-1 font-mono"
                                      :value="old('mac_address', $asset->mac_address)" required maxlength="17"
                                      x-model="mac"
                                      x-on:input="mac = formatMac($event.target.value)" />
                        <x-input-error class="mt-2" :messages="$errors->get('mac_address')" />
                    </div>

                    <div>
                        <x-input-label for="ip_address" :value="__('IP Address (opsional)')" />
                        <x-text-input id="ip_address" name="ip_address" type="text" class="mt-1 font-mono"
                                      :value="old('ip_address', $asset->ip_address)" maxlength="45" />
                        <x-input-error class="mt-2" :messages="$errors->get('ip_address')" />
                    </div>
                </div>
            </x-card>

            <x-card :title="__('Sistem Operasi')"
                    :description="__('Komponen fisik dikelola di bagian Komponen Terpasang pada halaman detail.')">
                <div>
                    <x-input-label for="specs_os" :value="__('Sistem Operasi (opsional)')" />
                    <x-text-input id="specs_os" name="specs[os]" type="text" class="mt-1"
                                  :value="old('specs.os', $asset->specs['os'] ?? '')" maxlength="100"
                                  placeholder="{{ __('Contoh: Windows 11 Pro 64-bit') }}" />
                    <x-input-error class="mt-2" :messages="$errors->get('specs.os')" />
                </div>
            </x-card>

            <x-card :title="__('Status')">
                <div>
                    <x-input-label for="status" :value="__('Status Aset')" />
                    <select id="status" name="status" required
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $asset->status->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('status')" />

                    @if ($asset->status === \App\Enums\AssetStatus::Assigned)
                        <p class="mt-2 flex items-start gap-2 text-xs text-amber-600">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                            {{ __('Aset sedang dipegang karyawan. Untuk mengembalikannya ke Available, gunakan fitur Return.') }}
                        </p>
                    @endif
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('assets.show', $asset) }}">
                    <x-secondary-button>{{ __('Batal') }}</x-secondary-button>
                </a>
                <x-primary-button>{{ __('Perbarui Aset') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
