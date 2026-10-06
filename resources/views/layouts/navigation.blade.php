@php
    $isAdmin = auth()->user()?->isAdmin();

    $links = [
        [
            'label' => __('Dashboard'),
            'route' => 'dashboard',
            'active' => request()->routeIs('dashboard'),
            'icon' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
        ],
        [
            'label' => __('Aset'),
            'route' => 'assets.index',
            'active' => request()->routeIs('assets.*'),
            'icon' => 'M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25',
        ],
        [
            'label' => __('Komponen'),
            'route' => 'components.index',
            'active' => request()->routeIs('components.*'),
            'icon' => 'M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z',
        ],
        [
            'label' => __('Karyawan'),
            'route' => 'employees.index',
            'active' => request()->routeIs('employees.*'),
            'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
        ],
        [
            'label' => __('Departemen'),
            'route' => 'departments.index',
            'active' => request()->routeIs('departments.*'),
            'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
        ],
    ];
    @endphp

{{-- Sidebar --}}
<aside x-cloak
x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
class="fixed inset-y-0 left-0 z-40 w-64 transform border-r border-slate-200 bg-white transition-transform duration-200 ease-in-out lg:translate-x-0">
{{-- Brand --}}
<div class="flex h-16 items-center gap-3 border-b border-slate-200 px-5">
    <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}"
         class="h-9 w-9 shrink-0 rounded-lg object-contain"
         width="36" height="36">
    <div class="min-w-0">
        <p class="truncate text-sm font-semibold text-slate-900">IT Asset</p>
        <p class="truncate text-xs text-slate-500">Management</p>
    </div>

    <button type="button" x-on:click="sidebarOpen = false"
    class="ms-auto rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 lg:hidden">
    <span class="sr-only">{{ __('Tutup menu') }}</span>
    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
</button>
</div>

{{-- Menu --}}
<nav class="flex flex-col gap-1 px-3 py-4">
    @foreach ($links as $link)
        @if (Route::has($link['route']))
            <a href="{{ route($link['route']) }}"
            class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
            {{ $link['active']
            ? 'bg-indigo-50 text-indigo-700 '
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 ' }}">
            <svg class="h-5 w-5 shrink-0 {{ $link['active'] ? 'text-indigo-600 ' : 'text-slate-400 group-hover:text-slate-500 ' }}"
            fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}" />
        </svg>
        {{ $link['label'] }}
    </a>
@endif
@endforeach

{{-- Menu khusus admin --}}
@if ($isAdmin)
    <p class="mt-4 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
        {{ __('Kelola') }}
    </p>

    <a href="{{ route('assets.create') }}"
    class="group flex items-center gap-3 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
    </svg>
    {{ __('Tambah Aset') }}
</a>

<a href="{{ route('assets.trashed') }}"
class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
{{ request()->routeIs('assets.trashed')
? 'bg-indigo-50 text-indigo-700 '
: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 ' }}">
<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
</svg>
{{ __('Aset Terhapus') }}
</a>

<a href="{{ route('components.create') }}"
class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
{{ request()->routeIs('components.create')
? 'bg-indigo-50 text-indigo-700 '
: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 ' }}">
<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
</svg>
{{ __('Tambah Komponen') }}
</a>

<a href="{{ route('components.trashed') }}"
class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
{{ request()->routeIs('components.trashed')
? 'bg-indigo-50 text-indigo-700 '
: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 ' }}">
<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
</svg>
{{ __('Komponen Terhapus') }}
</a>
@endif
</nav>
</aside>
