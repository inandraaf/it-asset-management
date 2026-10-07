<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Manajemen Akun') }}</h1>
                <p class="hidden text-sm text-slate-500 sm:block">
                    {{ __('Kelola akun yang dapat mengakses sistem') }}
                </p>
            </div>

            <a href="{{ route('users.create') }}">
                <x-primary-button type="button">{{ __('Tambah Akun') }}</x-primary-button>
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-card :padded="false">
            <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-end gap-3 p-5">
                <div class="min-w-[220px] flex-1">
                    <x-input-label for="q" :value="__('Cari')" />
                    <x-text-input id="q" name="q" type="search" class="mt-1"
                                  :value="request('q')" placeholder="{{ __('Nama atau nama pengguna...') }}" />
                </div>

                <div class="min-w-[160px]">
                    <x-input-label for="role" :value="__('Peran')" />
                    <select id="role" name="role" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('Semua peran') }}</option>
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <x-primary-button>{{ __('Terapkan') }}</x-primary-button>

                @if (request()->filled('q') || request()->filled('role'))
                    <a href="{{ route('users.index') }}">
                        <x-secondary-button>{{ __('Reset') }}</x-secondary-button>
                    </a>
                @endif
            </form>
        </x-card>

        <x-card :padded="false">
            @if ($users->isEmpty())
                <x-empty-state
                    :title="__('Belum ada akun')"
                    :description="__('Tambahkan akun untuk memberi akses ke sistem.')"
                    icon="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Nama') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Nama Pengguna') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Peran') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($users as $user)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </span>
                                            <span class="text-sm font-medium text-slate-900">{{ $user->name }}</span>
                                            @if ($user->is(auth()->user()))
                                                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700">{{ __('Anda') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-slate-600">
                                        {{ $user->username }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        @if ($user->isAdmin())
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                                {{ $user->role->label() }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                                {{ $user->role->label() }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                        <div class="inline-flex items-center gap-3">
                                            <a href="{{ route('users.edit', $user) }}"
                                               class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                                {{ __('Edit') }}
                                            </a>

                                            @unless ($user->is(auth()->user()))
                                                <form method="POST" action="{{ route('users.destroy', $user) }}"
                                                      data-confirm="Hapus akun {{ $user->username }}?"
                                                      data-confirm-button="Hapus Akun">
                                                    @csrf
                                                    @method('delete')
                                                    <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-800 hover:underline">
                                                        {{ __('Hapus') }}
                                                    </button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 p-4">
                    {{ $users->links() }}
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
