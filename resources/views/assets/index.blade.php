<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Aset') }}</h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Daftar PC & Laptop beserta pemegangnya') }}
        </p>
    </x-slot>

    <div class="space-y-6">
        {{-- Filter --}}
        <x-card :padded="false">
            <form method="GET" action="{{ route('assets.index') }}" class="space-y-4 p-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <x-input-label for="q" :value="__('Cari')" />
                        <div class="relative mt-1">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                            </span>
                            <x-text-input id="q" name="q" type="search" class="pl-9"
                            :value="request('q')"
                            placeholder="{{ __('Kode, MAC, IP, merek, atau nama user...') }}" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="status" :value="__('Status')" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua status') }}</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="type" :value="__('Jenis')" />
                        <select id="type" name="type" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua jenis') }}</option>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <x-input-label for="department_id" :value="__('Departemen Pemegang')" />
                        <select id="department_id" name="department_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua departemen') }}</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>
                                    {{ $department->nama_dept }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end gap-2 lg:col-span-3">
                        <x-primary-button>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            {{ __('Terapkan') }}
                        </x-primary-button>

                        @if (request()->filled('q') || request()->filled('status') || request()->filled('type') || request()->filled('department_id'))
                            <a href="{{ route('assets.index') }}">
                                <x-secondary-button>{{ __('Reset') }}</x-secondary-button>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </x-card>

        {{-- Tabel --}}
        <x-card :padded="false">
            @if ($assets->isEmpty())
                <x-empty-state
                :title="__('Belum ada aset yang cocok')"
                :description="__('Coba ubah filter, atau tambahkan aset baru.')"
                icon="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25">
                @if (auth()->user()->isAdmin())
                    <x-slot name="action">
                        <a href="{{ route('assets.create') }}">
                            <x-primary-button type="button">{{ __('Tambah Aset') }}</x-primary-button>
                        </a>
                    </x-slot>
                @endif
            </x-empty-state>
            @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Kode Aset') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Jenis') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Spesifikasi') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('MAC') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('IP') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Pengguna') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Status') }}</th>
                            @if (auth()->user()->isAdmin())
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Aksi') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($assets as $asset)
                            @php($holder = $asset->activeAssignment?->employee)
                            <tr class="transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <a href="{{ route('assets.show', $asset) }}"
                                           class="font-mono text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                            {{ $asset->asset_code }}
                                        </a>
                                        @if ($asset->hostname)
                                            <div class="font-mono text-xs text-slate-500">{{ $asset->hostname }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                            {{ $asset->type->label() }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-slate-600">
                                        <div class="max-w-xs truncate" title="{{ $asset->specSummary(6) }}">
                                            {{ $asset->specSummary() }}
                                        </div>
                                    </td>
                            <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-slate-600">
                                {{ $asset->mac_address }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-slate-600">
                                {{ $asset->ip_address ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                @if ($holder)
                                    <div class="text-sm font-medium text-slate-900">{{ $holder->nama }}</div>
                                    <div class="text-xs text-slate-500">{{ $holder->department->nama_dept }}</div>
                                    @else
                                    <span class="text-sm text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <x-status-badge :status="$asset->status" />
                                </td>
                                @if (auth()->user()->isAdmin())
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                        <div class="inline-flex items-center gap-3">
                                            <a href="{{ route('assets.edit', $asset) }}"
                                            class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                            {{ __('Edit') }}
                                        </a>

                                        <form method="POST" action="{{ route('assets.destroy', $asset) }}"
                                        data-confirm-name="{{ $asset->asset_code }}"
                                        onsubmit="return confirm('Hapus aset ' + this.dataset.confirmName + '?')">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-800 hover:underline">
                                            {{ __('Hapus') }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="border-t border-slate-200 p-4">
        {{ $assets->links() }}
    </div>
@endif
</x-card>
</div>
</x-app-layout>
