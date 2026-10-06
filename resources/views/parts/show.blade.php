<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('components.index') }}"
               class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <span class="sr-only">{{ __('Kembali') }}</span>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>
            <h1 class="font-mono text-lg font-semibold text-slate-900">{{ $part->component_code }}</h1>
            <x-component-status-badge :value="$part->status" />
        </div>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ $part->category->label() }} · {{ $part->fullName() }}
        </p>
    </x-slot>

    <div class="space-y-6">
        {{-- Lokasi saat ini --}}
        <div x-data="{ showRemove: false }">
        <x-card :padded="false">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">{{ __('Lokasi Saat Ini') }}</h3>

                @if (auth()->user()->isAdmin())
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($currentInstallation?->asset)
                            <a href="{{ route('assets.show', $currentInstallation->asset) }}">
                                <x-secondary-button type="button">{{ __('Lihat Host') }}</x-secondary-button>
                            </a>
                            <a href="{{ route('components.move.create', $part) }}">
                                <x-secondary-button type="button">{{ __('Pindah') }}</x-secondary-button>
                            </a>
                            <x-danger-button type="button" x-on:click.prevent="showRemove = ! showRemove">
                                {{ __('Lepas') }}
                            </x-danger-button>
                        @elseif ($part->status === \App\Enums\ComponentStatus::InStock)
                            <a href="{{ route('components.attach.create', $part) }}">
                                <x-primary-button type="button">{{ __('Pasang ke Host') }}</x-primary-button>
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            <div class="p-5">
                @if ($currentInstallation?->asset)
                    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-4">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Host') }}</dt>
                            <dd class="mt-1 font-mono text-sm text-slate-900">{{ $currentInstallation->asset->asset_code }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Merek Host') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ $currentInstallation->asset->brand }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Tanggal Pasang') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ $currentInstallation->installed_date->format('d M Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Durasi') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ $currentInstallation->durationInDays() }} {{ __('hari') }}</dd>
                        </div>
                    </dl>

                    @if (auth()->user()->isAdmin())
                        <div x-show="showRemove" x-cloak x-transition
                             class="mt-6 border-t border-slate-200 pt-6">
                            <form method="POST" action="{{ route('components.remove', $currentInstallation) }}" class="max-w-lg space-y-4">
                                @csrf

                                @error('installation')
                                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">
                                        {{ $message }}
                                    </div>
                                @enderror

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="removed_date" :value="__('Tanggal Lepas')" />
                                        <x-text-input id="removed_date" name="removed_date" type="date" class="mt-1"
                                                      :value="old('removed_date', now()->format('Y-m-d'))" required />
                                        <x-input-error class="mt-2" :messages="$errors->get('removed_date')" />
                                    </div>

                                    <div>
                                        <x-input-label for="remove_notes" :value="__('Catatan (opsional)')" />
                                        <x-text-input id="remove_notes" name="notes" type="text" class="mt-1" maxlength="1000"
                                                      placeholder="{{ __('Kondisi saat dilepas...') }}" :value="old('notes')" />
                                        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <x-primary-button>{{ __('Konfirmasi Lepas') }}</x-primary-button>
                                    <x-secondary-button type="button" x-on:click="showRemove = false">{{ __('Batal') }}</x-secondary-button>
                                </div>
                            </form>
                        </div>
                    @endif
                @else
                    <p class="text-sm text-slate-500">{{ __('Komponen berada di gudang IT, belum terpasang.') }}</p>
                @endif
            </div>
        </x-card>

        </div>

        {{-- Informasi komponen --}}
        <x-card :title="__('Informasi Komponen')">
            <dl class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                @php
                    $info = [
                        ['label' => __('Kategori'), 'value' => $part->category->label()],
                        ['label' => __('Merek'), 'value' => $part->brand],
                        ['label' => __('Model'), 'value' => $part->model ?? '—'],
                        ['label' => __('Nomor Seri'), 'value' => $part->serial_number ?? '—', 'mono' => true],
                    ];
                @endphp

                @foreach ($info as $field)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $field['label'] }}</dt>
                        <dd class="mt-1 text-sm text-slate-900 {{ ($field['mono'] ?? false) ? 'font-mono' : '' }}">{{ $field['value'] }}</dd>
                    </div>
                @endforeach

                @foreach ($part->filledSpecs() as $specLabel => $specValue)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $specLabel }}</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $specValue }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($part->notes)
                <div class="mt-5 border-t border-slate-200 pt-5">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Catatan') }}</dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $part->notes }}</dd>
                </div>
            @endif
        </x-card>

        {{-- Riwayat pemasangan --}}
        <x-card :title="__('Riwayat Pemasangan')" :padded="false">
            @if ($history->isEmpty())
                <x-empty-state
                    :title="__('Belum ada riwayat pemasangan')"
                    :description="__('Riwayat tercatat setelah komponen dipasang ke sebuah host.')"
                    icon="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Host') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Pasang') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Lepas') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Durasi') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Catatan') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($history as $installation)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        @if ($installation->asset)
                                            <a href="{{ route('assets.show', $installation->asset) }}"
                                               class="font-mono text-sm text-indigo-600 hover:underline">{{ $installation->asset->asset_code }}</a>
                                            <div class="text-xs text-slate-500">{{ $installation->asset->brand }}</div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                        {{ $installation->installed_date->format('d M Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-sm">
                                        @if ($installation->isActive())
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                                {{ __('Terpasang') }}
                                            </span>
                                        @else
                                            <span class="text-slate-600">{{ $installation->removed_date?->format('d M Y') }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                        {{ $installation->durationInDays() }} {{ __('hari') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-sm text-slate-500">
                                        {{ \Illuminate\Support\Str::limit($installation->notes, 60) ?: '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 p-4">
                    {{ $history->links() }}
                </div>
            @endif
        </x-card>

        @if (auth()->user()->isAdmin())
            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('components.edit', $part) }}">
                    <x-secondary-button>{{ __('Edit Komponen') }}</x-secondary-button>
                </a>

                <form method="POST" action="{{ route('components.destroy', $part) }}"
                      data-confirm-name="{{ $part->component_code }}"
                      onsubmit="return confirm('Hapus komponen ' + this.dataset.confirmName + '?')">
                    @csrf
                    @method('delete')
                    <x-danger-button>{{ __('Hapus Komponen') }}</x-danger-button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
