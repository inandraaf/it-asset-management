<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">
            {{ __('Tambah Maintenance') }}
        </h2>
    </x-slot>

    <div class="py-12"
        x-data="{
            selectedEmployee: '',
            selectedAsset: '',
            selectedComponent: '',
            assetComponents: {},
            filteredAssets: [],
            init() {
                this.assetComponents = @json({{
                    $assignments->groupBy('asset_id')->mapWithKeys(function ($group, $assetId) {
                        return [$assetId => $group->map(function ($a) {
                            return [
                                'asset_id' => $a->asset_id,
                                'employee_id' => $a->employee_id,
                                'asset_code' => $a->asset->asset_code,
                                'components' => ($a->component_installations ?? [])->map(fn($ci) => [
                                    'id' => $ci->component_id,
                                    'component_code' => $ci->component->component_code ?? null,
                                ]),
                            ];
                        })->first();
                    })
                }});
            },
            updateFilteredAssets() {
                this.filteredAssets = Object.values(this.assetComponents).filter(a => a.employee_id == this.selectedEmployee);
                this.selectedAsset = '';
                this.selectedComponent = '';
            }
        }"
        x-init="init()"
        x-cloak>
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-slate-200">
                    <form method="POST" action="{{ route('maintenance.store') }}">
                        @csrf
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Karyawan</label>
                                <select x-model="selectedEmployee" @change="updateFilteredAssets()"
                                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- Pilih Karyawan --</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="asset_id" class="block text-sm font-medium text-slate-700">Aset</label>
                                <select name="asset_id" id="asset_id"
                                    x-model="selectedAsset"
                                    @change="selectedComponent = '';"
                                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required>
                                    <option value="" disabled>Pilih aset</option>
                                    <template x-for="asset in filteredAssets" :key="asset.asset_id">
                                        <option :value="asset.asset_id" x-text="asset.asset_code"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label for="component_id" class="block text-sm font-medium text-slate-700">Komponen</label>
                                <select name="component_id" id="component_id"
                                    x-model="selectedComponent"
                                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- Pilih Komponen --</option>
                                    <template x-for="component in (assetComponents[selectedAsset]?.components || [])" :key="component.id">
                                        <option :value="component.id" x-text="component.component_code"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label for="performed_at" class="block text-sm font-medium text-slate-700">Tanggal</label>
                                <input type="date" name="performed_at" id="performed_at"
                                    value="{{ date('Y-m-d') }}"
                                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required>
                            </div>
                            <div>
                                <label for="cost" class="block text-sm font-medium text-slate-700">Biaya</label>
                                <input type="number" name="cost" id="cost" step="0.01"
                                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="col-span-1 md:col-span-2">
                                <label for="description" class="block text-sm font-medium text-slate-700">Deskripsi</label>
                                <textarea name="description" id="description" rows="3"
                                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>