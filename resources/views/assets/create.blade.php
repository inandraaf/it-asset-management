<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Tambah Aset') }}</h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Daftarkan aset baru (komputer atau perangkat departemen)') }}
        </p>
    </x-slot>

    @php
        // Baris komponen baru yang gagal validasi → dipulihkan ke Alpine.
        $oldNewParts = collect(old('components.new', []))
            ->map(fn ($row, $index) => [
                'key' => $index,
                'category' => $row['category'] ?? 'ram',
                'brand' => $row['brand'] ?? '',
                'model' => $row['model'] ?? '',
                'serial_number' => $row['serial_number'] ?? '',
                'specs' => is_array($row['specs'] ?? null) ? $row['specs'] : [],
            ])
            ->values();
    @endphp

    <div class="mx-auto max-w-3xl space-y-6"
         x-data="{
            type: @js(old('type', 'PC')),
            mac: @js(old('mac_address')),
            formatMac(value) {
                const hex = value.toUpperCase().replace(/[^0-9A-F]/g, '').slice(0, 12);
                return hex.replace(/(.{2})(?=.)/g, '$1:');
            },
            // Rakit komponen (X3).
            assembleTab: 'new',
            newParts: @js($oldNewParts),
            stockIds: @js(array_map('intval', old('components.stock', []))),
            newPartSeq: {{ $oldNewParts->count() }},
            addNewPart() {
                this.newParts.push({
                    key: this.newPartSeq++,
                    category: 'ram', brand: '', model: '', serial_number: '',
                    specs: {},
                });
            },
            removeNewPart(index) {
                this.newParts.splice(index, 1);
            },
            toggleStock(id) {
                const at = this.stockIds.indexOf(id);
                if (at === -1) {
                    this.stockIds.push(id);
                } else {
                    this.stockIds.splice(at, 1);
                }
            },
         }">
        <form method="POST" action="{{ route('assets.store') }}" autocomplete="off" class="space-y-6">
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

                        {{-- Lokasi fisik: hanya CCTV (X2) --}}
                        <div x-show="type === 'CCTV'" x-cloak>
                            <x-input-label for="location" :value="__('Lokasi')" />
                            <x-text-input id="location" name="location" type="text" class="mt-1"
                                          :value="old('location')" maxlength="150"
                                          placeholder="{{ __('Lobby, Parkiran, Gudang...') }}" />
                            <p class="mt-1 text-xs text-slate-500">{{ __('Titik pemasangan CCTV ini.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('location')" />
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
                    :description="__('Komponen fisik dapat langsung dirakit pada bagian Susun Komponen di bawah.')">
                <div>
                    <x-input-label for="specs_os" :value="__('Sistem Operasi (opsional)')" />
                    <x-text-input id="specs_os" name="specs[os]" type="text" class="mt-1"
                                  :value="old('specs.os')" maxlength="100"
                                  placeholder="{{ __('Contoh: Windows 11 Pro 64-bit') }}" />
                    <x-input-error class="mt-2" :messages="$errors->get('specs.os')" />
                </div>
            </x-card>

            {{-- Rakit komponen saat aset dibuat (X3). Hanya untuk komputer. --}}
            <x-card :title="__('Susun Komponen')"
                    x-show="type === 'PC' || type === 'Laptop'" x-cloak
                    :description="__('Opsional. Tambah komponen pengadaan baru dan/atau pilih dari gudang — semuanya dipasang saat aset disimpan.')">
                {{-- fieldset di-disable untuk non-komputer: kartu disembunyikan
                     `x-show`, tetapi kontrolnya tetap ada di DOM dan akan ikut
                     ter-submit bila tidak di-disable. --}}
                <fieldset x-bind:disabled="! ['PC', 'Laptop'].includes(type)"
                          class="m-0 min-w-0 space-y-5 border-0 p-0">
                <div class="space-y-5">
                    @php
                        $assemblyErrors = collect($errors->getMessages())
                            ->filter(fn ($messages, $key) => str_starts_with($key, 'components'))
                            ->flatten();
                    @endphp

                    @if ($assemblyErrors->isNotEmpty())
                        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            <ul class="list-disc space-y-1 pl-4">
                                @foreach ($assemblyErrors as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Tab --}}
                    <div class="flex gap-2 border-b border-slate-200">
                        <button type="button" x-on:click="assembleTab = 'new'"
                                class="rounded-t-lg px-4 py-2 text-sm font-medium transition"
                                x-bind:class="assembleTab === 'new'
                                    ? 'border-b-2 border-indigo-600 text-indigo-700'
                                    : 'text-slate-500 hover:text-slate-700'">
                            {{ __('Komponen Baru') }}
                            <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs" x-text="newParts.length"></span>
                        </button>
                        <button type="button" x-on:click="assembleTab = 'stock'"
                                class="rounded-t-lg px-4 py-2 text-sm font-medium transition"
                                x-bind:class="assembleTab === 'stock'
                                    ? 'border-b-2 border-indigo-600 text-indigo-700'
                                    : 'text-slate-500 hover:text-slate-700'">
                            {{ __('Dari Gudang') }}
                            <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs" x-text="stockIds.length"></span>
                        </button>
                    </div>

                    {{-- Tab: Komponen Baru --}}
                    <div x-show="assembleTab === 'new'" class="space-y-4">
                        <template x-for="(item, index) in newParts" :key="item.key">
                            <div class="space-y-4 rounded-lg border border-slate-200 p-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-slate-700">
                                        {{ __('Komponen') }} #<span x-text="index + 1"></span>
                                    </span>
                                    <button type="button" x-on:click="removeNewPart(index)"
                                            class="text-sm font-medium text-rose-600 hover:text-rose-800 hover:underline">
                                        {{ __('Hapus') }}
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input-label :value="__('Kategori')" />
                                        <select x-model="item.category" :name="`components[new][${index}][category]`"
                                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @foreach ($componentCategories as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label :value="__('Merek')" />
                                        <input type="text" x-model="item.brand" :name="`components[new][${index}][brand]`"
                                               maxlength="100" placeholder="Kingston, Samsung..."
                                               class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <x-input-label :value="__('Model (opsional)')" />
                                        <input type="text" x-model="item.model" :name="`components[new][${index}][model]`"
                                               maxlength="150"
                                               class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <x-input-label :value="__('Nomor Seri (opsional)')" />
                                        <input type="text" x-model="item.serial_number" :name="`components[new][${index}][serial_number]`"
                                               maxlength="100"
                                               class="mt-1 block w-full rounded-lg border-slate-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                </div>

                                {{-- Field spesifikasi per kategori. Fieldset di-DISABLE saat
                                     tidak aktif agar key `specs` yang sama (capacity/type pada
                                     RAM & Storage) tidak terkirim ganda (lihat W1). --}}
                                @foreach ($componentSpecMap as $categoryValue => $meta)
                                    <fieldset x-show="item.category === @js($categoryValue)"
                                              x-bind:disabled="item.category !== @js($categoryValue)"
                                              class="m-0 min-w-0 border-0 p-0">
                                        @if (empty($meta['keys']))
                                            <p class="text-sm text-slate-500">{{ __('Tidak ada field spesifikasi khusus untuk kategori ini.') }}</p>
                                        @else
                                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                @foreach ($meta['keys'] as $key => $field)
                                                    <div>
                                                        <x-input-label :value="$field['label']" />
                                                        @if (! empty($field['options']))
                                                            <select x-model="item.specs[@js($key)]"
                                                                    :name="`components[new][${index}][specs][{{ $key }}]`"
                                                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                                <option value="">{{ __('-- Pilih --') }}</option>
                                                                @foreach ($field['options'] as $option)
                                                                    <option value="{{ $option }}">{{ $option }}</option>
                                                                @endforeach
                                                            </select>
                                                        @else
                                                            <input type="text" x-model="item.specs[@js($key)]"
                                                                   :name="`components[new][${index}][specs][{{ $key }}]`"
                                                                   maxlength="100" placeholder="{{ $field['placeholder'] }}"
                                                                   class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </fieldset>
                                @endforeach
                            </div>
                        </template>

                        <button type="button" x-on:click="addNewPart()"
                                class="inline-flex items-center gap-2 rounded-lg border border-dashed border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-indigo-400 hover:text-indigo-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            {{ __('Tambah Komponen Baru') }}
                        </button>
                    </div>

                    {{-- Tab: Dari Gudang --}}
                    <div x-show="assembleTab === 'stock'" x-cloak class="space-y-3">
                        @if ($stockComponents->isEmpty())
                            <p class="text-sm text-slate-500">{{ __('Tidak ada komponen di gudang saat ini.') }}</p>
                        @else
                            <div class="max-h-80 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200">
                                @foreach ($stockComponents as $component)
                                    <label class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-slate-50">
                                        <input type="checkbox" value="{{ $component->id }}"
                                               x-on:change="toggleStock({{ $component->id }})"
                                               x-bind:checked="stockIds.includes({{ $component->id }})"
                                               class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="flex-1">
                                            <span class="block font-mono text-sm text-slate-900">{{ $component->component_code }}</span>
                                            <span class="block text-xs text-slate-500">
                                                [{{ $component->category->label() }}] {{ $component->fullName() }}
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            {{-- Input tersembunyi: hanya id terpilih yang dikirim. --}}
                            <template x-for="id in stockIds" :key="id">
                                <input type="hidden" name="components[stock][]" :value="id">
                            </template>
                        @endif
                    </div>
                </div>
                </fieldset>
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
