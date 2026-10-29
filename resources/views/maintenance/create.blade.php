<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">
            {{ __('Tambah Maintenance') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-slate-200">
                    <form method="POST" action="{{ route('maintenance.store') }}">
                        @csrf
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label for="asset_id" class="block text-sm font-medium text-slate-700">Aset</label>
                                <select name="asset_id" id="asset_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach($assets as $asset)
                                        <option value="{{ $asset->id }}">{{ $asset->asset_code }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="component_id" class="block text-sm font-medium text-slate-700">Komponen (Opsional)</label>
                                <select name="component_id" id="component_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- Pilih Komponen --</option>
                                    @foreach($components as $component)
                                        <option value="{{ $component->id }}">{{ $component->component_code }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="performed_at" class="block text-sm font-medium text-slate-700">Tanggal</label>
                                <input type="date" name="performed_at" id="performed_at" value="{{ date('Y-m-d') }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            </div>
                            <div>
                                <label for="cost" class="block text-sm font-medium text-slate-700">Biaya</label>
                                <input type="number" name="cost" id="cost" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="col-span-1 md:col-span-2">
                                <label for="description" class="block text-sm font-medium text-slate-700">Deskripsi</label>
                                <textarea name="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
