@props(['status'])

@php
    $classes = match ($status) {
        \App\Enums\AssetStatus::Available => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 ',
        \App\Enums\AssetStatus::Assigned => 'bg-blue-100 text-blue-700 ring-blue-600/20 ',
        \App\Enums\AssetStatus::InRepair => 'bg-amber-100 text-amber-700 ring-amber-600/20 ',
        \App\Enums\AssetStatus::Retired => 'bg-slate-100 text-slate-600 ring-slate-500/20 ',
        default => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    };

    $dot = match ($status) {
        \App\Enums\AssetStatus::Available => 'bg-emerald-500',
        \App\Enums\AssetStatus::Assigned => 'bg-blue-500',
        \App\Enums\AssetStatus::InRepair => 'bg-amber-500',
        \App\Enums\AssetStatus::Retired => 'bg-slate-400',
        default => 'bg-slate-400',
    };
    @endphp

<span {{ $attributes->merge(['class' =>"inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {$classes}"]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>
    {{ $status->label() }}
</span>
