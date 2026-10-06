<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('components.show', $part) }}"
               class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <span class="sr-only">{{ __('Kembali') }}</span>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>
            <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Pasang ke Host') }}</h1>
            <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">{{ $part->component_code }}</span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        <x-card :title="__('Komponen')">
            <dl class="grid grid-cols-2 gap-5 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Kategori') }}</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $part->category->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Merek / Model') }}</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $part->fullName() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Serial') }}</dt>
                    <dd class="mt-1 font-mono text-sm text-slate-900">{{ $part->serial_number ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <form method="POST" action="{{ route('components.attach.store', $part) }}" class="space-y-6">
            @csrf

            <x-card :title="__('Host Tujuan')">
                <div class="space-y-6">
                    @error('component_id')
                        <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">
                            {{ $message }}
                        </div>
                    @enderror

                    <div>
                        <x-input-label for="asset_id" :value="__('Host')" />
                        <select id="asset_id" name="asset_id" required
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('-- Pilih Host --') }}</option>
                            @foreach ($assets as $option)
                                <option value="{{ $option->id }}" @selected((string) old('asset_id') === (string) $option->id)>
                                    {{ $option->asset_code }} — {{ $option->brand }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('asset_id')" />
                    </div>

                    <div>
                        <x-input-label for="installed_date" :value="__('Tanggal Pasang')" />
                        <x-text-input id="installed_date" name="installed_date" type="date" class="mt-1"
                                      :value="old('installed_date', now()->format('Y-m-d'))" required />
                        <x-input-error class="mt-2" :messages="$errors->get('installed_date')" />
                    </div>

                    <div>
                        <x-input-label for="notes" :value="__('Catatan (opsional)')" />
                        <textarea id="notes" name="notes" rows="3" maxlength="1000"
                                  class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="{{ __('Alasan pemasangan, kondisi, dsb.') }}">{{ old('notes') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </div>
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('components.show', $part) }}">
                    <x-secondary-button>{{ __('Batal') }}</x-secondary-button>
                </a>
                <x-primary-button>{{ __('Pasang Komponen') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
