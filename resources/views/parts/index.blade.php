<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Komponen') }}</h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Part komputer beserta lokasi pemasangannya') }}
        </p>
    </x-slot>

    <div class="space-y-6">
        {{-- Filter --}}
        <x-card :padded="false">
            <form method="GET" action="{{ route('components.index') }}" class="space-y-4 p-5">
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
                                          placeholder="{{ __('Kode, serial, merek, atau model...') }}" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="category" :value="__('Kategori')" />
                        <select id="category" name="category" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua kategori') }}</option>
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
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
                </div>

                <div class="flex items-center gap-2">
                    <x-primary-button>{{ __('Terapkan') }}</x-primary-button>

                    @if (request()->filled('q') || request()->filled('category') || request()->filled('status'))
                        <a href="{{ route('components.index') }}">
                            <x-secondary-button>{{ __('Reset') }}</x-secondary-button>
                        </a>
                    @endif
                </div>
            </form>
        </x-card>

        {{-- Tabel --}}
        <x-card :padded="false">
            @if ($components->isEmpty())
                <x-empty-state
                    :title="__('Belum ada komponen')"
                    :description="__('Catat part komputer untuk mulai melacak lokasinya.')"
                    icon="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z">
                    @if (auth()->user()->isAdmin())
                        <x-slot name="action">
                            <a href="{{ route('components.create') }}">
                                <x-primary-button type="button">{{ __('Tambah Komponen') }}</x-primary-button>
                            </a>
                        </x-slot>
                    @endif
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Kode') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Kategori') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Merek / Model') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Serial') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Lokasi') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Status') }}</th>
                                @if (auth()->user()->isAdmin())
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Aksi') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($components as $item)
                                @php($host = $item->activeInstallation?->asset)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <a href="{{ route('components.show', $item) }}"
                                           class="font-mono text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                            {{ $item->component_code }}
                                        </a>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                            {{ $item->category->label() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                        {{ $item->fullName() }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-slate-500">
                                        {{ $item->serial_number ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-sm">
                                        @if ($host)
                                            <a href="{{ route('assets.show', $host) }}"
                                               class="font-mono text-indigo-600 hover:underline">{{ $host->asset_code }}</a>
                                        @else
                                            <span class="text-slate-500">{{ $item->locationLabel() }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <x-component-status-badge :value="$item->status" />
                                    </td>
                                    @if (auth()->user()->isAdmin())
                                        <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                            <div class="inline-flex items-center gap-3">
                                                <a href="{{ route('components.edit', $item) }}"
                                                   class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                                    {{ __('Edit') }}
                                                </a>
                                                <form method="POST" action="{{ route('components.destroy', $item) }}"
                                                      data-confirm-name="{{ $item->component_code }}"
                                                      onsubmit="return confirm('Hapus komponen ' + this.dataset.confirmName + '?')">
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
                    {{ $components->links() }}
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
