<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('employees.index') }}"
            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
            <span class="sr-only">{{ __('Kembali') }}</span>
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
        </a>
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ $employee->nama }}</h1>
    </div>
    <p class="hidden text-sm text-slate-500 sm:block">
        {{ $employee->department->nama_dept }} · {{ $employee->nip }}
    </p>
</x-slot>

<div class="space-y-6">
    <x-card :title="__('Profil')">
        <dl class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('NIP') }}</dt>
                <dd class="mt-1 font-mono text-sm text-slate-900">{{ $employee->nip }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Nama') }}</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $employee->nama }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Departemen') }}</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $employee->department->nama_dept }}</dd>
            </div>
        </dl>
    </x-card>

    <x-card :title="__('Aset yang Sedang Dipegang')" :padded="false">
        @if ($activeAssignments->isEmpty())
            <x-empty-state
            :title="__('Tidak ada aset aktif')"
            :description="__('Karyawan ini tidak sedang memegang aset.')"
            icon="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25" />
            @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Kode Aset') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Jenis') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Merek') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Tanggal Assign') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($activeAssignments as $assignment)
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <a href="{{ route('assets.show', $assignment->asset) }}"
                                        class="font-mono text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                        {{ $assignment->asset->asset_code }}
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                        {{ $assignment->asset->type->label() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                    {{ $assignment->asset->brandLabel() }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                    {{ $assignment->assigned_date->format('d M Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card :title="__('Riwayat Pemakaian Aset')" :padded="false">
        @if ($history->isEmpty())
            <x-empty-state
            :title="__('Belum ada riwayat')"
            :description="__('Riwayat muncul setelah karyawan pernah di-assign aset.')"
            icon="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Kode Aset') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Serahkan') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Tarik Kembali') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($history as $assignment)
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <a href="{{ route('assets.show', $assignment->asset) }}"
                                        class="font-mono text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                        {{ $assignment->asset->asset_code }}
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                    {{ $assignment->assigned_date->format('d M Y') }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                    {{ $assignment->returned_date?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <x-assignment-status :active="$assignment->isActive()" />
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
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('employees.index') }}">
                    <x-secondary-button>{{ __('Kembali') }}</x-secondary-button>
                </a>
                <a href="{{ route('employees.edit', $employee) }}">
                    <x-primary-button type="button">{{ __('Edit Karyawan') }}</x-primary-button>
                </a>
            </div>
        @endif
    </div>
</x-app-layout>
