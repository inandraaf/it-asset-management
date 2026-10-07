<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Departemen') }}</h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Master data departemen perusahaan') }}
        </p>
    </x-slot>

    <div class="space-y-6">
        <x-card :padded="false">
            <form method="GET" action="{{ route('departments.index') }}" class="flex flex-wrap items-end gap-3 p-5">
                <div class="min-w-[220px] flex-1">
                    <x-input-label for="q" :value="__('Cari departemen')" />
                    <div class="relative mt-1">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </span>
                        <x-text-input id="q" name="q" type="search" class="pl-9"
                        :value="request('q')" placeholder="{{ __('Nama departemen...') }}" />
                    </div>
                </div>

                <x-primary-button>{{ __('Cari') }}</x-primary-button>

                @if (request()->filled('q'))
                    <a href="{{ route('departments.index') }}">
                        <x-secondary-button>{{ __('Reset') }}</x-secondary-button>
                    </a>
                @endif
            </form>
        </x-card>

        <x-card :padded="false">
            @if ($departments->isEmpty())
                <x-empty-state
                :title="__('Belum ada departemen terdaftar')"
                :description="__('Tambahkan departemen untuk mulai mencatat karyawan dan aset.')"
                icon="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21">
                @if (auth()->user()->isAdmin())
                    <x-slot name="action">
                        <a href="{{ route('departments.create') }}">
                            <x-primary-button type="button">{{ __('Tambah Departemen') }}</x-primary-button>
                        </a>
                    </x-slot>
                @endif
            </x-empty-state>
            @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Nama Departemen') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Jumlah Karyawan') }}</th>
                            @if (auth()->user()->isAdmin())
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Aksi') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($departments as $department)
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                            </svg>
                                        </span>
                                        <span class="text-sm font-medium text-slate-900">{{ $department->nama_dept }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                    <span class="inline-flex min-w-[2rem] justify-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                        {{ $department->employees_count }}
                                    </span>
                                </td>
                                @if (auth()->user()->isAdmin())
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                        <div class="inline-flex items-center gap-3">
                                            <a href="{{ route('departments.edit', $department) }}"
                                            class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                            {{ __('Edit') }}
                                        </a>

                                        <form method="POST" action="{{ route('departments.destroy', $department) }}"
                                                      data-confirm="Hapus departemen {{ $department->nama_dept }}?"
                                                      data-confirm-button="Hapus Departemen">
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
        {{ $departments->links() }}
    </div>
@endif
</x-card>
</div>
</x-app-layout>
