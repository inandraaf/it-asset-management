@props(['active'])

{{-- Badge status penugasan aset: Aktif (belum dikembalikan) atau Selesai. --}}
@if ($active)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 ']) }}>
        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
        {{ __('Aktif') }}
    </span>
    @else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/20 ']) }}>
        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
        {{ __('Selesai') }}
    </span>
@endif
