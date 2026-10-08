<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Edit Komponen') }}</h1>
            <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">
                {{ $part->component_code }}
            </span>
        </div>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ $part->category->label() }} · {{ $part->brand }}
        </p>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <form method="POST" action="{{ route('components.update', $part) }}" autocomplete="off" class="space-y-6">
            @csrf
            @method('put')

            <x-card :title="__('Identitas Komponen')">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label :value="__('Kode Komponen')" />
                        <p class="mt-1 font-mono text-sm text-slate-900">{{ $part->component_code }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Dibuat sistem, tidak dapat diubah.') }}</p>
                    </div>

                    <div>
                        <x-input-label :value="__('Kategori')" />
                        <p class="mt-1 text-sm text-slate-900">{{ $part->category->label() }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Tidak dapat diubah (menentukan prefix kode).') }}</p>
                    </div>

                    <div>
                        <x-input-label for="brand" :value="__('Merek')" />
                        <x-text-input id="brand" name="brand" type="text" class="mt-1"
                                      :value="old('brand', $part->brand)" required maxlength="100" />
                        <x-input-error class="mt-2" :messages="$errors->get('brand')" />
                    </div>

                    <div>
                        <x-input-label for="model" :value="__('Model (opsional)')" />
                        <x-text-input id="model" name="model" type="text" class="mt-1"
                                      :value="old('model', $part->model)" maxlength="150" />
                        <x-input-error class="mt-2" :messages="$errors->get('model')" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label for="serial_number" :value="__('Nomor Seri (opsional)')" />
                        <x-text-input id="serial_number" name="serial_number" type="text" class="mt-1 font-mono"
                                      :value="old('serial_number', $part->serial_number)" maxlength="100" />
                        <x-input-error class="mt-2" :messages="$errors->get('serial_number')" />
                    </div>
                </div>
            </x-card>

            <x-card :title="__('Spesifikasi')">
                @php
                    $keys = $specMap[$part->category->value]['keys'] ?? [];
                    $advanced = $specMap[$part->category->value]['advanced'] ?? [];
                @endphp

                @if (empty($keys) && empty($advanced))
                    <p class="text-sm text-slate-500">{{ __('Tidak ada field spesifikasi khusus untuk kategori ini.') }}</p>
                @else
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        @foreach ($keys as $key => $field)
                            <div>
                                <x-input-label :for="'spec_'.$key" :value="$field['label']" />
                                <x-spec-field :key="$key" :field="$field" :value="$part->specs[$key] ?? null" />
                                <x-input-error class="mt-2" :messages="$errors->get('specs.'.$key)" />
                            </div>
                        @endforeach
                    </div>

                    @if (! empty($advanced))
                        <details class="mt-5 rounded-lg border border-slate-200" @if (collect(array_keys($advanced))->contains(fn ($k) => $errors->has('specs.'.$k))) open @endif>
                            <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50">
                                {{ __('Spesifikasi lanjutan (opsional)') }}
                            </summary>

                            <div class="grid grid-cols-1 gap-6 border-t border-slate-200 p-4 sm:grid-cols-2">
                                @foreach ($advanced as $key => $field)
                                    <div>
                                        <x-input-label :for="'spec_'.$key" :value="$field['label']" />
                                        <x-spec-field :key="$key" :field="$field" :value="$part->specs[$key] ?? null" />
                                        <x-input-error class="mt-2" :messages="$errors->get('specs.'.$key)" />
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                @endif
            </x-card>

            <x-card :title="__('Status & Catatan')">
                <div class="space-y-6">
                    <div>
                        <x-input-label for="status" :value="__('Status')" />
                        <select id="status" name="status" required
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $part->status->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('status')" />

                        @if ($part->status === \App\Enums\ComponentStatus::Installed)
                            <p class="mt-2 flex items-start gap-2 text-xs text-amber-600">
                                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                </svg>
                                {{ __('Komponen sedang terpasang. Untuk mengembalikannya ke gudang, gunakan fitur Lepas.') }}
                            </p>
                        @endif
                    </div>

                    <div>
                        <x-input-label for="notes" :value="__('Catatan (opsional)')" />
                        <textarea id="notes" name="notes" rows="3" maxlength="1000"
                                  class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $part->notes) }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </div>
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('components.show', $part) }}">
                    <x-secondary-button>{{ __('Batal') }}</x-secondary-button>
                </a>
                <x-primary-button>{{ __('Perbarui Komponen') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
