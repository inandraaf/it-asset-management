<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('assets.show', $asset) }}"
            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
            <span class="sr-only">{{ __('Kembali') }}</span>
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
        </a>
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Transfer Aset') }}</h1>
        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">
            {{ $asset->asset_code }}
        </span>
    </div>
</x-slot>

<div class="mx-auto max-w-2xl space-y-6">
    <x-card :title="__('Pemegang Saat Ini')">
        <dl class="grid grid-cols-2 gap-5 sm:grid-cols-4">
            @php
                $info = [
                    ['label' => __('Kode'), 'value' => $asset->asset_code, 'mono' => true],
                    ['label' => __('Pemegang'), 'value' => $current->employee->nama],
                    ['label' => __('Departemen'), 'value' => $current->employee->department->nama_dept],
                    ['label' => __('Sejak'), 'value' => $current->assigned_date->format('d M Y')],
                ];
                @endphp

            @foreach ($info as $item)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item['label'] }}</dt>
                    <dd class="mt-1 text-sm text-slate-900 {{ ($item['mono'] ?? false) ? 'font-mono' : '' }}">
                        {{ $item['value'] }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </x-card>

    <form method="POST" action="{{ route('assets.transfer.store', $asset) }}" class="space-y-6">
        @csrf

        <x-card :title="__('Tujuan Transfer')">
            <div class="space-y-6">
                @error('asset_id')
                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">
                        {{ $message }}
                    </div>
                @enderror

                <div>
                    <x-input-label for="employee_id" :value="__('Karyawan Tujuan')" />
                    <select id="employee_id" name="employee_id" required
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('-- Pilih Karyawan Tujuan --') }}</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>
                            {{ $employee->nama }} — {{ $employee->department->nama_dept }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('employee_id')" />
                </div>

                <div>
                    <x-input-label for="transfer_date" :value="__('Tanggal Transfer')" />
                    <x-text-input id="transfer_date" name="transfer_date" type="date" class="mt-1"
                    :value="old('transfer_date', now()->format('Y-m-d'))" required />
                    <x-input-error class="mt-2" :messages="$errors->get('transfer_date')" />
                    </div>

                    <div>
                        <x-input-label for="notes" :value="__('Catatan (opsional)')" />
                        <textarea id="notes" name="notes" rows="3" maxlength="1000"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="{{ __('Alasan transfer, kondisi perangkat, dsb.') }}">{{ old('notes') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </div>

                    <div class="flex items-start gap-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                        <span>{{ __('Transfer akan menutup riwayat pemegang saat ini dan membuat riwayat baru untuk karyawan tujuan.') }}</span>
                    </div>
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('assets.show', $asset) }}">
                    <x-secondary-button>{{ __('Batal') }}</x-secondary-button>
                </a>
                <x-primary-button>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                    </svg>
                    {{ __('Transfer Aset') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
