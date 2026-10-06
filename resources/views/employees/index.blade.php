<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Karyawan') }}</h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Daftar karyawan beserta aset yang dipegang') }}
        </p>
    </x-slot>

    <div class="space-y-6">
        <x-card :padded="false">
            <form method="GET" action="{{ route('employees.index') }}" class="space-y-4 p-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <x-input-label for="q" :value="__('Cari')" />
                        <div class="relative mt-1">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                            </span>
                            <x-text-input id="q" name="q" type="search" class="pl-9"
                            :value="request('q')" placeholder="{{ __('Nama atau NIP...') }}" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="department_id" :value="__('Departemen')" />
                        <select id="department_id" name="department_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua departemen') }}</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>
                                    {{ $department->nama_dept }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <x-primary-button>{{ __('Terapkan') }}</x-primary-button>

                    @if (request()->filled('q') || request()->filled('department_id'))
                        <a href="{{ route('employees.index') }}">
                            <x-secondary-button>{{ __('Reset') }}</x-secondary-button>
                        </a>
                    @endif
                </div>
            </form>
        </x-card>

        <x-card :padded="false">
            @if ($employees->isEmpty())
                <x-empty-state
                :title="__('Belum ada karyawan terdaftar')"
                :description="__('Tambahkan karyawan untuk mulai menugaskan aset.')"
                icon="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z">
                @if (auth()->user()->isAdmin())
                    <x-slot name="action">
                        <a href="{{ route('employees.create') }}">
                            <x-primary-button type="button">{{ __('Tambah Karyawan') }}</x-primary-button>
                        </a>
                    </x-slot>
                @endif
            </x-empty-state>
            @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Nama') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('NIP') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Departemen') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Aset Dipegang') }}</th>
                            @if (auth()->user()->isAdmin())
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Aksi') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($employees as $employee)
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                            {{ strtoupper(substr($employee->nama, 0, 1)) }}
                                        </span>
                                        <a href="{{ route('employees.show', $employee) }}"
                                        class="text-sm font-medium text-slate-900 hover:text-indigo-600 hover:underline">
                                        {{ $employee->nama }}
                                    </a>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-slate-600">
                                {{ $employee->nip }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                    {{ $employee->department->nama_dept }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                <span class="inline-flex min-w-[2rem] justify-center rounded-md px-2 py-0.5 text-xs font-semibold
                                {{ $employee->active_assignments_count > 0
                                ? 'bg-blue-100 text-blue-700 '
                                : 'bg-slate-100 text-slate-500 ' }}">
                                {{ $employee->active_assignments_count }}
                            </span>
                        </td>
                        @if (auth()->user()->isAdmin())
                            <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                <div class="inline-flex items-center gap-3">
                                    <a href="{{ route('employees.edit', $employee) }}"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                    {{ __('Edit') }}
                                </a>

                                <form method="POST" action="{{ route('employees.destroy', $employee) }}"
                                data-confirm-name="{{ $employee->nama }}"
                                onsubmit="return confirm('Hapus karyawan ' + this.dataset.confirmName + '?')">
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
    {{ $employees->links() }}
</div>
@endif
</x-card>
</div>
</x-app-layout>
