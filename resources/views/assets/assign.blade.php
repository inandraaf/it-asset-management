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
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Serahkan Aset') }}</h1>
        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">
            {{ $asset->asset_code }}
        </span>
    </div>
</x-slot>

<div class="mx-auto max-w-2xl space-y-6">
    <x-card :title="__('Aset')">
        <dl class="grid grid-cols-2 gap-5 sm:grid-cols-4">
            @php
                $info = [
                    ['label' => __('Kode'), 'value' => $asset->asset_code, 'mono' => true],
                    ['label' => __('Jenis'), 'value' => $asset->type->label()],
                    ['label' => __('Merek'), 'value' => $asset->brandLabel()],
                    ['label' => __('Spesifikasi'), 'value' => collect($asset->specs)->filter()->implode(' · ') ?: '—'],
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

    <form method="POST" action="{{ route('assets.assign.store', $asset) }}" class="space-y-6">
        @csrf

        <x-card :title="__('Penugasan')">
            <div class="space-y-6">
                @error('asset_id')
                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">
                        {{ $message }}
                    </div>
                @enderror

                <div>
                    <x-input-label for="employee_id" :value="__('Karyawan')" />
                    <select id="employee_id" name="employee_id" required
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('-- Pilih Karyawan --') }}</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>
                            {{ $employee->nama }} — {{ $employee->department->nama_dept }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('employee_id')" />
                </div>

                <div>
                    <x-input-label for="assigned_date" :value="__('Tanggal Assign')" />
                    <x-text-input id="assigned_date" name="assigned_date" type="date" class="mt-1"
                    :value="old('assigned_date', now()->format('Y-m-d'))" required />
                    <x-input-error class="mt-2" :messages="$errors->get('assigned_date')" />
                    </div>

                    <div>
                        <x-input-label for="notes" :value="__('Catatan (opsional)')" />
                        <textarea id="notes" name="notes" rows="3" maxlength="1000"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="{{ __('Kondisi perangkat, kelengkapan charger, dsb.') }}">{{ old('notes') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </div>
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('assets.show', $asset) }}">
                    <x-secondary-button>{{ __('Batal') }}</x-secondary-button>
                </a>
                <x-primary-button>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    {{ __('Serahkan Aset') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
