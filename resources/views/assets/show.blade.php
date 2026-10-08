<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('assets.index') }}"
            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
            <span class="sr-only">{{ __('Kembali') }}</span>
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
        </a>
        <h1 class="font-mono text-lg font-semibold text-slate-900">{{ $asset->asset_code }}</h1>
        <x-status-badge :status="$asset->status" />
        </div>
        <p class="hidden text-sm text-slate-500 sm:block">
            {{ $asset->brandLabel() }} · {{ $asset->type->label() }}
        </p>
    </x-slot>

    <div class="space-y-6">
        {{-- Pemegang saat ini / aksi alokasi — hanya untuk PC/Laptop.
             CCTV & Printer tidak punya pemegang karyawan (U3a). --}}
        @if ($asset->type->isComputer())
        <div x-data="{ showReturn: false }">
            <x-card :padded="false">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-900">{{ __('Pemegang Saat Ini') }}</h3>

                    @if (auth()->user()->isAdmin())
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($currentAssignment)
                                <a href="{{ route('assets.transfer.create', $asset) }}">
                                    <x-secondary-button>
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                        </svg>
                                        {{ __('Pindahkan') }}
                                    </x-secondary-button>
                                </a>

                                <x-danger-button type="button" x-on:click.prevent="showReturn = ! showReturn">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                                    </svg>
                                    {{ __('Tarik Kembali') }}
                                </x-danger-button>
                                @elseif ($asset->status === \App\Enums\AssetStatus::Available)
                                <a href="{{ route('assets.assign.create', $asset) }}">
                                    <x-primary-button type="button">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                                        </svg>
                                        {{ __('Serahkan') }}
                                    </x-primary-button>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="p-5">
                    @if ($currentAssignment)
                        <dl class="grid grid-cols-1 gap-5 sm:grid-cols-4">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Nama') }}</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-900">{{ $currentAssignment->employee->nama ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Departemen') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $currentAssignment->employee?->department->nama_dept ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Tanggal Assign') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $currentAssignment->assigned_date->format('d M Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Durasi') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $currentAssignment->durationInDays() }} {{ __('hari') }}</dd>
                            </div>
                        </dl>

                        @if (auth()->user()->isAdmin())
                            <div x-show="showReturn" x-cloak x-transition
                            class="mt-6 border-t border-slate-200 pt-6">
                            <form method="POST" action="{{ route('assignments.return', $currentAssignment) }}" class="max-w-lg space-y-4">
                                @csrf

                                @error('assignment')
                                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">
                                        {{ $message }}
                                    </div>
                                @enderror

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="returned_date" :value="__('Tanggal Return')" />
                                        <x-text-input id="returned_date" name="returned_date" type="date" class="mt-1"
                                        :value="old('returned_date', now()->format('Y-m-d'))" required />
                                        <x-input-error class="mt-2" :messages="$errors->get('returned_date')" />
                                        </div>

                                        <div>
                                            <x-input-label for="return_notes" :value="__('Catatan (opsional)')" />
                                            <x-text-input id="return_notes" name="notes" type="text" class="mt-1" maxlength="1000"
                                            placeholder="{{ __('Kondisi saat dikembalikan...') }}" :value="old('notes')" />
                                            <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <x-primary-button>{{ __('Konfirmasi Return') }}</x-primary-button>
                                            <x-secondary-button type="button" x-on:click="showReturn = false">{{ __('Batal') }}</x-secondary-button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                            @else
                            <div class="flex items-center gap-3 text-sm text-slate-500">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                </span>
                                {{ __('Aset tidak sedang dipegang siapa pun.') }}
                            </div>
                        @endif
                    </div>
                </x-card>
            </div>
        @endif

        {{-- Informasi aset --}}
        <x-card :title="__('Informasi Aset')">
            <dl class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                @php
                    $info = [
                        ['label' => __('Jenis'), 'value' => $asset->type->label()],
                        ['label' => __('Merek & Model'), 'value' => $asset->brandLabel()],
                    ];

                    // Nama perangkat & OS hanya relevan untuk komputer (U3a).
                    if ($asset->type->isComputer()) {
                        $info[] = ['label' => __('Nama Komputer'), 'value' => $asset->hostname ?? '—', 'mono' => true];
                    }

                    $info[] = ['label' => __('MAC Address'), 'value' => $asset->mac_address ?? '—', 'mono' => true];
                    $info[] = ['label' => __('IP Address'), 'value' => $asset->ip_address ?? '—', 'mono' => true];

                    // Lokasi fisik hanya untuk CCTV (X2).
                    if ($asset->type->supportsLocation()) {
                        $info[] = ['label' => __('Lokasi'), 'value' => $asset->location ?? '—'];
                    }

                    if ($asset->type->isComputer()) {
                        $info[] = ['label' => __('Sistem Operasi'), 'value' => $asset->osLabel()];
                    }

                    // Departemen pemilik (Printer).
                    if ($asset->department) {
                        $info[] = ['label' => __('Departemen Pemilik'), 'value' => $asset->department->nama_dept];
                    }

                    $info[] = ['label' => __('Dicatat oleh'), 'value' => $asset->creator?->name ?? '—'];
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

        {{-- Kredensial & akses remote (S5).
             Terbuka untuk semua role agar staf EDP yang didelegasikan dapat
             mengeksekusi saat Admin IT tidak tersedia (U2). --}}
        @if ($asset->type->isComputer())
            <x-card :title="__('Akses Remote & Kredensial')"
                    :description="__('Satu aset dapat memiliki beberapa akun (mis. Admin, User Biasa, VNC).')"
                    :padded="false"
                    x-data="{ showSecrets: false, showAdd: false }">
                <x-slot name="actions">
                    <x-secondary-button type="button" x-on:click="showSecrets = ! showSecrets">
                        <span x-text="showSecrets ? @js(__('Sembunyikan')) : @js(__('Tampilkan'))"></span>
                    </x-secondary-button>
                    @if (auth()->user()->isAdmin())
                        <x-primary-button type="button" x-on:click="showAdd = ! showAdd">
                            {{ __('Tambah Kredensial') }}
                        </x-primary-button>
                    @endif
                </x-slot>

                @php($credentials = $asset->credentials)

                <div class="p-5">
                    @if ($credentials->isEmpty())
                        <p class="text-sm text-slate-500">{{ __('Belum ada kredensial yang dicatat untuk aset ini.') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Label') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Nama Pengguna') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Kata Sandi') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Catatan') }}</th>
                                        @if (auth()->user()->isAdmin())
                                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Aksi') }}</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($credentials as $credential)
                                        <tr class="transition hover:bg-slate-50">
                                            <td class="whitespace-nowrap px-4 py-3.5 text-sm font-medium text-slate-900">
                                                {{ $credential->label }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3.5 font-mono text-sm text-slate-700">
                                                {{ $credential->username ?? '—' }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3.5">
                                                <span x-show="! showSecrets" class="font-mono text-sm text-slate-400">••••••••</span>
                                                <span x-show="showSecrets" x-cloak class="font-mono text-sm text-slate-900">{{ $credential->password ?? '—' }}</span>
                                            </td>
                                            <td class="px-4 py-3.5 text-sm text-slate-500">
                                                {{ \Illuminate\Support\Str::limit($credential->notes, 50) ?: '—' }}
                                            </td>
                                            @if (auth()->user()->isAdmin())
                                                <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                                    <form method="POST" action="{{ route('credentials.destroy', $credential) }}"
                                                          data-confirm="Hapus kredensial {{ $credential->label }}?"
                                                          data-confirm-button="Hapus Kredensial">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-800 hover:underline">
                                                            {{ __('Hapus') }}
                                                        </button>
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <p class="mt-3 flex items-start gap-2 text-xs text-amber-600">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                            {{ __('Informasi ini terenkripsi dan hanya untuk Admin IT. Jangan sebarkan.') }}
                        </p>
                    @endif

                    {{-- Form tambah kredensial (hanya admin) --}}
                    @if (auth()->user()->isAdmin())
                    <div x-show="showAdd" x-cloak x-transition class="mt-5 border-t border-slate-200 pt-5">
                        <form method="POST" action="{{ route('assets.credentials.store', $asset) }}"
                              class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @csrf

                            <div>
                                <x-input-label for="cred_label" :value="__('Label')" />
                                <x-text-input id="cred_label" name="label" type="text" class="mt-1"
                                              required maxlength="100" placeholder="{{ __('Admin / User Biasa / VNC') }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('label')" />
                            </div>

                            <div>
                                <x-input-label for="cred_username" :value="__('Nama Pengguna (opsional)')" />
                                <x-text-input id="cred_username" name="username" type="text" class="mt-1 font-mono" maxlength="150" />
                                <x-input-error class="mt-2" :messages="$errors->get('username')" />
                            </div>

                            <div>
                                <x-input-label for="cred_password" :value="__('Kata Sandi (opsional)')" />
                                <x-text-input id="cred_password" name="password" type="text" class="mt-1 font-mono" maxlength="255" />
                                <x-input-error class="mt-2" :messages="$errors->get('password')" />
                            </div>

                            <div>
                                <x-input-label for="cred_notes" :value="__('Catatan (opsional)')" />
                                <x-text-input id="cred_notes" name="notes" type="text" class="mt-1" maxlength="1000"
                                              placeholder="{{ __('Alamat/port VNC, dsb.') }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                            </div>

                            <div class="sm:col-span-2 flex items-center gap-3">
                                <x-primary-button>{{ __('Simpan Kredensial') }}</x-primary-button>
                                <x-secondary-button type="button" x-on:click="showAdd = false">{{ __('Batal') }}</x-secondary-button>
                            </div>
                        </form>
                    </div>
                    @endif
                </div>
            </x-card>
        @endif

            {{-- Komponen terpasang — hanya untuk komputer (U3a) --}}
            @if ($asset->type->isComputer())
            <x-card :title="__('Komponen Terpasang')" :padded="false">
                @if (auth()->user()->isAdmin())
                    <x-slot name="actions">
                        @if ($installedComponents->isNotEmpty())
                            <a href="{{ route('components.bulk-move.create', $asset) }}">
                                <x-secondary-button type="button">{{ __('Pindah Komponen') }}</x-secondary-button>
                            </a>
                            <a href="{{ route('components.bulk-remove.create', $asset) }}">
                                <x-secondary-button type="button">{{ __('Lepas Komponen') }}</x-secondary-button>
                            </a>
                        @endif
                        <a href="{{ route('components.bulk-install.create', $asset) }}">
                            <x-primary-button type="button">{{ __('Pasang Komponen') }}</x-primary-button>
                        </a>
                    </x-slot>
                @endif

                @if ($installedComponents->isEmpty())
                    <x-empty-state
                        :title="__('Belum ada komponen terpasang')"
                        :description="__('Komponen yang dipasang ke PC ini akan tampil di sini.')"
                        icon="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Kode') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Kategori') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Merek / Model') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Serial') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Sejak') }}</th>
                                    @if (auth()->user()->isAdmin())
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Aksi') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            @foreach ($installedComponents as $installation)
                                @php($piece = $installation->component)
                                {{-- Satu tbody per komponen: state Alpine (panel Lepas) per baris. --}}
                                <tbody x-data="{ open: @js($errors->has('removed_date')) }" class="divide-y divide-slate-100">
                                    <tr class="transition hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-5 py-3.5">
                                            @if ($piece)
                                                <a href="{{ route('components.show', $piece) }}"
                                                   class="font-mono text-sm font-medium text-indigo-600 hover:underline">
                                                    {{ $piece->component_code }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5">
                                            @if ($piece)
                                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                                    {{ $piece->category->label() }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                            {{ $piece?->fullName() ?? '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-slate-500">
                                            {{ $piece?->serial_number ?? '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                            {{ $installation->installed_date->format('d M Y') }}
                                        </td>
                                        @if (auth()->user()->isAdmin())
                                            <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                                <div class="inline-flex items-center gap-3">
                                                    @if ($piece)
                                                        <a href="{{ route('components.move.create', ['component' => $piece->id, 'from' => 'asset']) }}"
                                                           class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                                            {{ __('Pindah') }}
                                                        </a>
                                                    @endif

                                                    <button type="button" x-on:click="open = ! open"
                                                            class="text-sm font-medium text-rose-600 hover:text-rose-800 hover:underline">
                                                        {{ __('Lepas') }}
                                                    </button>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>

                                    @if (auth()->user()->isAdmin())
                                        <tr x-show="open" x-cloak x-transition>
                                            <td colspan="6" class="bg-slate-50 px-5 py-4">
                                                <form method="POST" action="{{ route('components.remove', $installation) }}"
                                                      class="flex flex-wrap items-end gap-3">
                                                    @csrf
                                                    <input type="hidden" name="from" value="asset">

                                                    <div>
                                                        <x-input-label for="removed_date_{{ $installation->id }}" :value="__('Tanggal Lepas')" />
                                                        <x-text-input id="removed_date_{{ $installation->id }}" name="removed_date"
                                                                      type="date" class="mt-1"
                                                                      :value="old('removed_date', now()->format('Y-m-d'))" required />
                                                        <x-input-error class="mt-1" :messages="$errors->get('removed_date')" />
                                                    </div>

                                                    <div class="min-w-[200px] flex-1">
                                                        <x-input-label for="remove_notes_{{ $installation->id }}" :value="__('Catatan (opsional)')" />
                                                        <x-text-input id="remove_notes_{{ $installation->id }}" name="notes"
                                                                      type="text" class="mt-1" maxlength="1000"
                                                                      placeholder="{{ __('Kondisi saat dilepas...') }}" />
                                                    </div>

                                                    <div class="flex items-center gap-2">
                                                        <x-primary-button>{{ __('Konfirmasi Lepas') }}</x-primary-button>
                                                        <x-secondary-button type="button" x-on:click="open = false">{{ __('Batal') }}</x-secondary-button>
                                                    </div>
                                                </form>

                                                @error('installation')
                                                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                                                @enderror
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            @endforeach
                        </table>
                    </div>
                @endif
            </x-card>
            @endif

            {{-- Riwayat pemakaian — hanya untuk komputer (U3a) --}}
            @if ($asset->type->isComputer())
            <x-card :title="__('Riwayat Pemakaian')" :padded="false">
                @if ($history->isEmpty())
                    <x-empty-state
                    :title="__('Belum ada riwayat pemakaian')"
                    :description="__('Riwayat akan tercatat setelah aset di-assign ke karyawan.')"
                    icon="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Karyawan') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Serahkan') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Tarik Kembali') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Durasi') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Catatan') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($history as $assignment)
                                    <tr class="transition hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-5 py-3.5">
                                            <div class="text-sm font-medium text-slate-900">{{ $assignment->employee->nama ?? '—' }}</div>
                                            <div class="text-xs text-slate-500">{{ $assignment->employee?->department->nama_dept ?? '—' }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                            {{ $assignment->assigned_date->format('d M Y') }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-sm">
                                            @if ($assignment->isActive())
                                                <x-assignment-status :active="true" />
                                                @else
                                                <span class="text-slate-600">{{ $assignment->returned_date?->format('d M Y') }}</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                            {{ $assignment->durationInDays() }} {{ __('hari') }}
                                        </td>
                                        <td class="px-5 py-3.5 text-sm text-slate-500">
                                            {{ \Illuminate\Support\Str::limit($assignment->notes, 60) ?: '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-slate-200 p-4">
                        {{ $history->links() }}
                    </div>
                @endif
            </x-card>
            @endif

            @if (auth()->user()->isAdmin())
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <a href="{{ route('assets.edit', $asset) }}">
                        <x-secondary-button>{{ __('Edit Aset') }}</x-secondary-button>
                    </a>

                    <form method="POST" action="{{ route('assets.destroy', $asset) }}"
                                                      data-confirm="Hapus aset {{ $asset->asset_code }}?"
                                                      data-confirm-button="Hapus Aset">
                    @csrf
                    @method('delete')
                    <x-danger-button>{{ __('Hapus Aset') }}</x-danger-button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
