<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('assets.index') }}"
            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
            <span class="sr-only">{{ __('Kembali') }}</span>
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
        </a>
        <h1 class="font-mono text-lg font-semibold text-slate-900">{{ $asset->asset_code }}</h1>
        <x-status-badge :status="$asset->status" />
        </div>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ $asset->brand }} · {{ $asset->type->label() }}
        </p>
    </x-slot>

    <div class="space-y-6">
        {{-- Pemegang saat ini / aksi alokasi --}}
        <div x-data="{ showReturn: false }">
            <x-card :padded="false">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-900">{{ __('Pemegang Saat Ini') }}</h3>

                    @if (auth()->user()->isAdmin())
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($currentAssignment)
                                <a href="{{ route('assets.transfer.create', $asset) }}">
                                    <x-secondary-button>
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                        </svg>
                                        {{ __('Transfer') }}
                                    </x-secondary-button>
                                </a>

                                <x-danger-button type="button" x-on:click.prevent="showReturn = ! showReturn">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                                    </svg>
                                    {{ __('Return') }}
                                </x-danger-button>
                                @elseif ($asset->status === \App\Enums\AssetStatus::Available)
                                <a href="{{ route('assets.assign.create', $asset) }}">
                                    <x-primary-button type="button">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                                        </svg>
                                        {{ __('Assign') }}
                                    </x-primary-button>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="p-5">
                    @if ($currentAssignment)
                        <dl class="grid grid-cols-1 gap-5 sm:grid-cols-4">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Nama') }}</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-900">{{ $currentAssignment->employee->nama ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Departemen') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $currentAssignment->employee?->department->nama_dept ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Tanggal Assign') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $currentAssignment->assigned_date->format('d M Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Durasi') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $currentAssignment->durationInDays() }} {{ __('hari') }}</dd>
                            </div>
                        </dl>

                        @if (auth()->user()->isAdmin())
                            <div x-show="showReturn" x-cloak x-transition
                            class="mt-6 border-t border-slate-200 pt-6">
                            <form method="POST" action="{{ route('assignments.return', $currentAssignment) }}" class="max-w-lg space-y-4">
                                @csrf

                                @error('assignment')
                                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">
                                        {{ $message }}
                                    </div>
                                @enderror

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="returned_date" :value="__('Tanggal Return')" />
                                        <x-text-input id="returned_date" name="returned_date" type="date" class="mt-1"
                                        :value="old('returned_date', now()->format('Y-m-d'))" required />
                                        <x-input-error class="mt-2" :messages="$errors->get('returned_date')" />
                                        </div>

                                        <div>
                                            <x-input-label for="return_notes" :value="__('Catatan (opsional)')" />
                                            <x-text-input id="return_notes" name="notes" type="text" class="mt-1" maxlength="1000"
                                            placeholder="{{ __('Kondisi saat dikembalikan...') }}" :value="old('notes')" />
                                            <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <x-primary-button>{{ __('Konfirmasi Return') }}</x-primary-button>
                                            <x-secondary-button type="button" x-on:click="showReturn = false">{{ __('Batal') }}</x-secondary-button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                            @else
                            <div class="flex items-center gap-3 text-sm text-slate-500">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                </span>
                                {{ __('Aset tidak sedang dipegang siapa pun.') }}
                            </div>
                        @endif
                    </div>
                </x-card>
            </div>

        {{-- Informasi aset --}}
        <x-card :title="__('Informasi Aset')">
            <dl class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                @php
                    $info = [
                        ['label' => __('Jenis'), 'value' => $asset->type->label()],
                        ['label' => __('Merek & Model'), 'value' => $asset->brand],
                        ['label' => __('Nama Komputer'), 'value' => $asset->hostname ?? '—', 'mono' => true],
                        ['label' => __('MAC Address'), 'value' => $asset->mac_address, 'mono' => true],
                        ['label' => __('IP Address'), 'value' => $asset->ip_address ?? '—', 'mono' => true],
                        ['label' => __('Dicatat oleh'), 'value' => $asset->creator?->name ?? '—'],
                    ];
                @endphp

                @foreach ($info as $item)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item['label'] }}</dt>
                        <dd class="mt-1 text-sm text-slate-900 {{ ($item['mono'] ?? false) ? 'font-mono' : '' }}">
                            {{ $item['value'] }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-card>

        {{-- Spesifikasi --}}
        <x-card :title="__('Spesifikasi Komponen')">
            @php($specs = $asset->filledSpecs())

            @if ($specs === [])
                <p class="text-sm text-slate-500">{{ __('Belum ada spesifikasi yang dicatat.') }}</p>
            @else
                <dl class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($specs as $label => $value)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </x-card>

            {{-- Riwayat --}}
            <x-card :title="__('Riwayat Pemakaian')" :padded="false">
                @if ($history->isEmpty())
                    <x-empty-state
                    :title="__('Belum ada riwayat pemakaian')"
                    :description="__('Riwayat akan tercatat setelah aset di-assign ke karyawan.')"
                    icon="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Karyawan') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Assign') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Return') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Durasi') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Catatan') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($history as $assignment)
                                    <tr class="transition hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-5 py-3.5">
                                            <div class="text-sm font-medium text-slate-900">{{ $assignment->employee->nama ?? '—' }}</div>
                                            <div class="text-xs text-slate-500">{{ $assignment->employee?->department->nama_dept ?? '—' }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                            {{ $assignment->assigned_date->format('d M Y') }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-sm">
                                            @if ($assignment->isActive())
                                                <x-assignment-status :active="true" />
                                                @else
                                                <span class="text-slate-600">{{ $assignment->returned_date?->format('d M Y') }}</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                            {{ $assignment->durationInDays() }} {{ __('hari') }}
                                        </td>
                                        <td class="px-5 py-3.5 text-sm text-slate-500">
                                            {{ \Illuminate\Support\Str::limit($assignment->notes, 60) ?: '—' }}
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
                    <a href="{{ route('assets.edit', $asset) }}">
                        <x-secondary-button>{{ __('Edit Aset') }}</x-secondary-button>
                    </a>

                    <form method="POST" action="{{ route('assets.destroy', $asset) }}"
                    data-confirm-name="{{ $asset->asset_code }}"
                    onsubmit="return confirm('Hapus aset ' + this.dataset.confirmName + '?')">
                    @csrf
                    @method('delete')
                    <x-danger-button>{{ __('Hapus Aset') }}</x-danger-button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
