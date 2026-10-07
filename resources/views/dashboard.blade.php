<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-slate-900">
            {{ __('Dashboard') }}
        </h1>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ __('Ringkasan aset IT dan alokasinya') }}
        </p>
    </x-slot>

    <div class="space-y-6">
        {{-- Kartu statistik utama --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @php
                $cards = [
                    [
                        'label' => __('Total PC'),
                        'value' => $stats['total_pc'],
                        'icon' => 'M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25',
                        'tone' => 'indigo',
                    ],
                    [
                        'label' => __('Total Laptop'),
                        'value' => $stats['total_laptop'],
                        'icon' => 'M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V13.5zm0 2.25h.008v.008H8.25v-.008zm2.25-4.5h.008v.008H10.5v-.008zm0 2.25h.008v.008H10.5V13.5zm0 2.25h.008v.008H10.5v-.008zm2.25-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zm-6-8.25h9.75A2.25 2.25 0 0116.5 5.25v9a2.25 2.25 0 01-2.25 2.25h-9.75A2.25 2.25 0 012.25 14.25v-9A2.25 2.25 0 014.5 3z',
                        'tone' => 'sky',
                    ],
                    [
                        'label' => __('Total CCTV'),
                        'value' => $stats['total_cctv'],
                        'icon' => 'M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z',
                        'tone' => 'violet',
                    ],
                    [
                        'label' => __('Total Printer'),
                        'value' => $stats['total_printer'],
                        'icon' => 'M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5z',
                        'tone' => 'rose',
                    ],
                    [
                        'label' => __('Aset Terpakai'),
                        'value' => $stats['assigned'],
                        'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                        'tone' => 'emerald',
                    ],
                    [
                        'label' => __('Aset Menganggur'),
                        'value' => $stats['available'],
                        'icon' => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
                        'tone' => 'amber',
                    ],
                ];

                $tones = [
                    'indigo' => ['bg' => 'bg-indigo-50 ', 'text' => 'text-indigo-600 '],
                    'sky' => ['bg' => 'bg-sky-50 ', 'text' => 'text-sky-600 '],
                    'emerald' => ['bg' => 'bg-emerald-50 ', 'text' => 'text-emerald-600 '],
                    'amber' => ['bg' => 'bg-amber-50 ', 'text' => 'text-amber-600 '],
                    'violet' => ['bg' => 'bg-violet-50 ', 'text' => 'text-violet-600 '],
                    'rose' => ['bg' => 'bg-rose-50 ', 'text' => 'text-rose-600 '],
                ];
                @endphp

            @foreach ($cards as $card)
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div class="flex items-start justify-between">
                        <p class="text-sm font-medium text-slate-500">{{ $card['label'] }}</p>
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $tones[$card['tone']]['bg'] }} {{ $tones[$card['tone']]['text'] }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">
                        {{ number_format($card['value'], 0, ',', '.') }}
                    </p>
                </div>
            @endforeach
        </div>

        {{-- Statistik tambahan --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @php
                $secondary = [
                    ['label' => __('Dalam Perbaikan'), 'value' => $stats['in_repair']],
                    ['label' => __('Rusak'), 'value' => $stats['retired']],
                    ['label' => __('Total Aset'), 'value' => $stats['total_assets']],
                    ['label' => __('Total Komponen'), 'value' => $stats['total_components']],
                    ['label' => __('Komponen di Gudang'), 'value' => $stats['components_in_stock']],
                ];
            @endphp

            @foreach ($secondary as $item)
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item['label'] }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">
                        {{ number_format($item['value'], 0, ',', '.') }}
                    </p>
                </div>
            @endforeach
        </div>

        {{-- Ringkasan per departemen --}}
        {{-- Grafik (T5) --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card :title="__('Komposisi Aset per Jenis')">
                <div class="relative h-64">
                    <canvas id="chart-asset-type"></canvas>
                </div>
            </x-card>

            <x-card :title="__('Status Aset')">
                <div class="relative h-64">
                    <canvas id="chart-asset-status"></canvas>
                </div>
            </x-card>

            <x-card :title="__('Aset Terpakai per Departemen')">
                <div class="relative h-64">
                    <canvas id="chart-department"></canvas>
                </div>
            </x-card>

            <x-card :title="__('Komposisi Komponen')">
                <div class="relative h-64">
                    <canvas id="chart-component-category"></canvas>
                </div>
            </x-card>
        </div>

        <x-card :title="__('Ringkasan per Departemen')" :padded="false">
            @if ($byDepartment->isEmpty())
                <x-empty-state
                :title="__('Belum ada departemen')"
                :description="__('Tambahkan departemen untuk mulai mencatat aset.')"
                icon="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Departemen') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Karyawan') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Aset Terpakai') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($byDepartment as $department)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-3.5 text-sm font-medium text-slate-900">
                                        {{ $department->nama_dept }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right text-sm text-slate-600">
                                        {{ $department->employees_count }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right text-sm">
                                        <span class="inline-flex min-w-[2rem] justify-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                            {{ $department->assigned_assets_count }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        {{-- Alokasi terbaru --}}
        <x-card :title="__('Alokasi Terbaru')" :description="__('10 penugasan aset terakhir')" :padded="false">
            @if ($recentAssignments->isEmpty())
                <x-empty-state
                :title="__('Belum ada alokasi aset')"
                :description="__('Serahkan aset ke karyawan untuk melihat riwayatnya di sini.')"
                icon="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Karyawan') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Aset') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Tanggal') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentAssignments as $assignment)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <div class="text-sm font-medium text-slate-900">{{ $assignment->employee->nama ?? '—' }}</div>
                                        <div class="text-xs text-slate-500">{{ $assignment->employee?->department->nama_dept ?? '—' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-sm font-mono">
                                        @if ($assignment->asset)
                                            <a href="{{ route('assets.show', $assignment->asset) }}"
                                                class="text-indigo-600 hover:text-indigo-800 hover:underline">
                                                {{ $assignment->asset->asset_code }}
                                            </a>
                                            @else
                                            —
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                        {{ $assignment->assigned_date->format('d M Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <x-assignment-status :active="$assignment->isActive()" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

    {{-- Data untuk grafik (T5). Dilempar sebagai JSON agar aman dari escaping. --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const palette = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b'];

            const donut = (id, labels, values) => window.initItamChart(id, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{ data: values, backgroundColor: palette, borderWidth: 0 }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } },
                },
            });

            const bar = (id, labels, values, label) => window.initItamChart(id, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{ label, data: values, backgroundColor: '#6366f1', borderRadius: 4 }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { ticks: { font: { size: 10 } } },
                    },
                },
            });

            donut('chart-asset-type', @js($typeChart['labels']), @js($typeChart['values']));
            donut('chart-asset-status', @js($statusChart['labels']), @js($statusChart['values']));
            bar('chart-department', @js($departmentChart['labels']), @js($departmentChart['values']), @js(__('Aset Terpakai')));
            bar('chart-component-category', @js($categoryChart['labels']), @js($categoryChart['values']), @js(__('Jumlah Komponen')));
        });
    </script>
</x-app-layout>
