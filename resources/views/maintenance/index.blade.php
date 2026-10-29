<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">
            {{ __('Pemeliharaan') }}
        </h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Riwayat pemeliharaan aset IT') }}
        </p>
    </x-slot>

    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <form method="get" action="{{ route('maintenance.index') }}" class="flex items-center gap-3">
                    <label class="sr-only" for="asset_id">{{ __('Filter aset') }}</label>
                    <select id="asset_id" name="asset_id"
                        class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-sm focus:outline-none focus:ring-indigo-500">
                        <option value="" {{ $filterAsset ? '' : 'selected' }}>{{ __('Semua aset') }}</option>
                        @foreach ($assets as $a)
                            <option value="{{ $a->id }}" {{ (int) $filterAsset === (int) $a->id ? 'selected' : '' }}>
                                {{ $a->asset_code }} ({{ $a->type }})
                            </option>
                        @endforeach
                    </select>
                    <button type="submit"
                        class="rounded-lg bg-indigo-600 px-3 py-1 text-sm font-medium text-white hover:bg-indigo-700">
                        {{ __('Terapkan') }}
                    </button>
                    @if ($filterAsset)
                        <a href="{{ route('maintenance.index') }}"
                            class="text-sm text-slate-600 underline">{{ __('Reset') }}</a>
                    @endif
                </form>
            </div>
            <div>
                <a href="{{ route('maintenance.create') }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">
                    {{ __('Tambah Maintenance') }}
                </a>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-left">
                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">@lang('Aset')</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">@lang('Tanggal')</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">@lang('Deskripsi')</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">@lang('Biaya')</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">@lang('Teknisi')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr class="border-b border-slate-100 transition hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $item->asset?->asset_code ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $item->performed_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $item->description ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $item->cost !== null ? number_format($item->cost, 2, ',', '.') : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $item->performer?->name ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($items->isEmpty())
            <p class="py-8 text-center text-sm text-slate-500">{{ __('Belum ada catatan pemeliharaan.') }}</p>
        @endif
    </div>
</x-app-layout>
