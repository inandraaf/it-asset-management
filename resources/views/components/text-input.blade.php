@props(['disabled' => false])

<input @disabled($disabled) {!! $attributes->merge(['class' => 'block w-full rounded-lg border-slate-300 text-sm shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500 ']) !!}>
