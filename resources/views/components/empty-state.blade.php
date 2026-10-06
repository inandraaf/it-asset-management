@props(['title', 'description' => null, 'icon' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-3 px-6 py-14 text-center']) }}>
    @if ($icon)
        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
            </svg>
        </span>
    @endif

    <div>
        <p class="text-sm font-medium text-slate-900">{{ $title }}</p>
        @if ($description)
            <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
        @endif
    </div>

    @isset($action)
        <div class="mt-1">{{ $action }}</div>
    @endisset
</div>
