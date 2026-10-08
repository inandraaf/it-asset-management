@props(['summary', 'detail'])

{{-- Tooltip instan untuk ringkasan yang terpotong.
     `title` bawaan browser baru muncul setelah ~1 detik dan tidak bisa ditata,
     sehingga diganti tooltip Alpine yang muncul seketika saat hover/fokus.

     Di-teleport ke `body` dengan `position: fixed` karena berada di dalam
     kontainer `overflow-x-auto`; tooltip `position: absolute` biasa akan
     terpotong oleh kontainer tersebut.

     Logika penempatan ada di `window.itamTooltip()` (resources/js/app.js). --}}
<div
    x-data="itamTooltip()"
    x-on:mouseenter="show($el)"
    x-on:mouseleave="hide()"
    x-on:focusin="show($el)"
    x-on:focusout="hide()"
    tabindex="0"
    role="note"
    aria-label="{{ $detail }}"
    {{ $attributes->merge(['class' => 'relative cursor-help']) }}
>
    <div class="max-w-md line-clamp-2">{{ $summary }}</div>

    <template x-teleport="body">
        <div x-ref="tip" x-show="open" x-cloak x-transition.opacity.duration.75ms
             x-bind:style="`position: fixed; top: ${top}px; left: ${left}px; max-width: ${maxWidth}px;`"
             class="pointer-events-none z-[70] rounded-lg bg-slate-900 px-3 py-2 text-xs font-normal leading-relaxed text-white shadow-lg ring-1 ring-black/5">
            {{ $detail }}
        </div>
    </template>
</div>
