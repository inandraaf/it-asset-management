<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('assets.index') }}"
            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
            <span class="sr-only">{{ __('Kembali') }}</span>
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
        </a>
        <h1 class="truncate text-lg font-semibold text-slate-900">{{ __('Aset Terhapus') }}</h1>
    </div>
    <p class="hidden text-sm text-slate-500 sm:block">
        {{ __('Aset yang dihapus dapat dipulihkan kembali') }}
    </p>
</x-slot>

<div class="space-y-6">
    <x-card :padded="false">
        @if ($assets->isEmpty())
            <x-empty-state
            :title="__('Tidak ada aset yang dihapus')"
            :description="__('Aset yang Anda hapus akan muncul di sini dan bisa dipulihkan.')"
            icon="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0">
            <x-slot name="action">
                <a href="{{ route('assets.index') }}">
                    <x-secondary-button type="button">{{ __('Lihat Daftar Aset') }}</x-secondary-button>
                </a>
            </x-slot>
        </x-empty-state>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Kode Aset') }}</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('MAC') }}</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('IP') }}</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Dihapus') }}</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($assets as $asset)
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <div class="font-mono text-sm font-medium text-slate-900">{{ $asset->asset_code }}</div>
                                <div class="text-xs text-slate-500">{{ $asset->brand }} · {{ $asset->type->label() }}</div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-slate-600">
                                {{ $asset->mac_address }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-slate-600">
                                {{ $asset->ip_address ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">
                                {{ $asset->deleted_at->format('d M Y H:i') }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                <div class="inline-flex items-center gap-3">
                                    <form method="POST" action="{{ route('assets.restore', $asset->id) }}">
                                        @csrf
                                        <button type="submit" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                            {{ __('Pulihkan') }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('assets.force-delete', $asset->id) }}"
                                        data-confirm-name="{{ $asset->asset_code }}"
                                        onsubmit="return confirm('Hapus permanen aset ' + this.dataset.confirmName + '? Riwayat pemakaiannya juga akan ikut terhapus.')">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-800 hover:underline">
                                            {{ __('Hapus Permanen') }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 p-4">
            {{ $assets->links() }}
        </div>
    @endif
</x-card>
</div>
</x-app-layout>
