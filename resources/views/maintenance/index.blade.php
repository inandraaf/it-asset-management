@extends('layouts.app')

@section('title', __('Riwayat Pemeliharaan'))

@section('content')
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('Pemeliharaan') }}</h2>
            <select id="assetFilter" x-model="filterAsset" @change="reload()"
                class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-sm focus:outline-none focus:ring-indigo-500">
                <option value="" {{ !$filterAsset ? 'selected' : '' }}>{{ __('Semua aset') }}</option>
                @foreach ($assets as $a)
                    <option value="{{ $a->id }}" {{ $filterAsset === $a->id ? 'selected' : '' }}>
                        {{ $a->asset_code }} ({{ $a->type }})
                    </option>
                @endforeach
            </select>
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

<script setup>
const q = new URLSearchParams(window.location.search);
const filterAsset = q.get('asset_id') || '';
function reload() {
    const params = new URLSearchParams();
    if (filterAsset) params.set('asset_id', filterAsset);
    window.location.search = params.toString();
}
</script>
@endsection
