<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('assets.show', $asset) }}"
               class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <span class="sr-only">{{ __('Kembali') }}</span>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>
            <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Pasang Komponen') }}</h1>
            <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">{{ $asset->asset_code }}</span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        <x-card :title="__('Host')">
            <dl class="grid grid-cols-2 gap-5 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Kode') }}</dt>
                    <dd class="mt-1 font-mono text-sm text-slate-900">{{ $asset->asset_code }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Jenis') }}</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $asset->type->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Merek') }}</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $asset->brandLabel() }}</dd>
                </div>
            </dl>
        </x-card>

        @if ($components->isEmpty())
            <x-card>
                <x-empty-state
                    :title="__('Tidak ada komponen yang tersedia')"
                    :description="__('Semua komponen sedang terpasang, atau belum ada yang dicatat.')"
                    icon="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z">
                    <x-slot name="action">
                        <a href="{{ route('components.create') }}">
                            <x-primary-button type="button">{{ __('Tambah Komponen') }}</x-primary-button>
                        </a>
                    </x-slot>
                </x-empty-state>
            </x-card>
        @else
            <form method="POST" action="{{ route('components.install.store', $asset) }}" class="space-y-6">
                @csrf

                <x-card :title="__('Pilih Komponen')">
                    <div class="space-y-6">
                        @error('component_id')
                            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">
                                {{ $message }}
                            </div>
                        @enderror

                        <div>
                            <x-input-label for="component_id" :value="__('Komponen')" />
                            <select id="component_id" name="component_id" required
                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('-- Pilih Komponen --') }}</option>
                                @foreach ($components as $option)
                                    <option value="{{ $option->id }}" @selected((string) old('component_id') === (string) $option->id)>
                                        [{{ $option->category->label() }}] {{ $option->component_code }} — {{ $option->fullName() }}
                                        @if ($option->serial_number) ({{ $option->serial_number }}) @endif
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('component_id')" />
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
                    <a href="{{ route('assets.show', $asset) }}">
                        <x-secondary-button>{{ __('Batal') }}</x-secondary-button>
                    </a>
                    <x-primary-button>{{ __('Pasang Komponen') }}</x-primary-button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
