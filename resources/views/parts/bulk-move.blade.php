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
            <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Pindah Komponen Massal') }}</h1>
            <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">{{ $asset->asset_code }}</span>
        </div>
        <p class="hidden text-sm text-slate-500 sm:block">{{ __('Pindahkan beberapa komponen ke host lain sekaligus.') }}</p>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if ($installations->isEmpty())
            <x-card>
                <x-empty-state
                    :title="__('Tidak ada komponen terpasang')"
                    :description="__('Aset ini belum memiliki komponen untuk dipindahkan.')"
                    icon="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25" />
            </x-card>
        @else
            <form method="POST" action="{{ route('components.bulk-move.store', $asset) }}" class="space-y-6">
                @csrf

                <x-card :title="__('Pilih Komponen')" :padded="false">
                    @error('items')
                        <div class="border-b border-rose-200 bg-rose-50 px-5 py-3 text-sm text-rose-800">{{ $message }}</div>
                    @enderror

                    <div class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                        @foreach ($installations as $installation)
                            @php($piece = $installation->component)
                            <label class="flex cursor-pointer items-center gap-3 px-5 py-3 hover:bg-slate-50">
                                <input type="checkbox" name="items[]" value="{{ $installation->id }}"
                                       @checked(in_array($installation->id, old('items', [])))
                                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="flex-1">
                                    <span class="block font-mono text-sm text-slate-900">{{ $piece?->component_code }}</span>
                                    <span class="block text-xs text-slate-500">
                                        [{{ $piece?->category->label() }}] {{ $piece?->fullName() }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </x-card>

                <x-card :title="__('Host Tujuan & Tanggal')">
                    <div class="space-y-6">
                        <div>
                            <x-input-label for="target_asset_id" :value="__('Host Tujuan')" />
                            <select id="target_asset_id" name="target_asset_id" required
                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('-- Pilih Host Tujuan --') }}</option>
                                @foreach ($assets as $option)
                                    <option value="{{ $option->id }}" @selected((string) old('target_asset_id') === (string) $option->id)>
                                        {{ $option->asset_code }} — {{ $option->brandLabel() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('target_asset_id')" />
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <x-input-label for="date" :value="__('Tanggal Pindah')" />
                                <x-text-input id="date" name="date" type="date" class="mt-1"
                                              :value="old('date', now()->format('Y-m-d'))" required />
                                <x-input-error class="mt-2" :messages="$errors->get('date')" />
                            </div>
                            <div>
                                <x-input-label for="notes" :value="__('Catatan (opsional)')" />
                                <x-text-input id="notes" name="notes" type="text" class="mt-1" maxlength="1000" />
                            </div>
                        </div>
                    </div>
                </x-card>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('assets.show', $asset) }}">
                        <x-secondary-button>{{ __('Batal') }}</x-secondary-button>
                    </a>
                    <x-primary-button>{{ __('Pindahkan Terpilih') }}</x-primary-button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
