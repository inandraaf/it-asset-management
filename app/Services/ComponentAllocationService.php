<?php

namespace App\Services;

use App\Enums\ComponentStatus;
use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentInstallation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logika pemasangan komponen: pasang, lepas, dan pindah host.
 *
 * Semua operasi dibungkus transaksi + row lock agar invariant
 * "satu komponen terpasang di satu host" tetap terjaga.
 *
 * @see dokumentasi/14-manajemen-komponen.md §7, §13
 */
class ComponentAllocationService
{
    /**
     * Pasang komponen ke sebuah host.
     *
     * @throws ValidationException bila komponen tidak berstatus In Stock
     *                             atau masih terpasang di host lain
     */
    public function install(Component $component, Asset $asset, CarbonInterface $date, ?string $notes = null): ComponentInstallation
    {
        return DB::transaction(function () use ($component, $asset, $date, $notes) {
            $locked = Component::whereKey($component->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ComponentStatus::InStock) {
                throw ValidationException::withMessages([
                    'component_id' => 'Komponen tidak tersedia untuk dipasang (status saat ini: '.$locked->status->value.').',
                ]);
            }

            if ($locked->installations()->whereNull('removed_date')->exists()) {
                throw ValidationException::withMessages([
                    'component_id' => 'Komponen masih terpasang di host lain. Lakukan Lepas terlebih dahulu.',
                ]);
            }

            // K11: tanggal pasang tidak boleh sebelum tanggal host dibuat.
            // Dibandingkan per-hari: `installed_date` bertipe date (tengah
            // malam), sedangkan `created_at` menyimpan jam.
            if ($date->copy()->startOfDay()->lt($asset->created_at->copy()->startOfDay())) {
                throw ValidationException::withMessages([
                    'installed_date' => 'Tanggal pasang tidak boleh sebelum tanggal host dibuat.',
                ]);
            }
            $installation = $locked->installations()->create([
                'asset_id' => $asset->getKey(),
                'installed_date' => $date,
                'notes' => $notes,
                'installed_by' => auth()->id(),
            ]);

            $locked->update(['status' => ComponentStatus::Installed]);

            return $installation;
        });
    }

    /**
     * Lepas komponen dari host; status kembali In Stock.
     *
     * @throws ValidationException bila pemasangan sudah ditutup atau
     *                             tanggal lepas mendahului tanggal pasang
     */
    public function remove(ComponentInstallation $installation, CarbonInterface $date, ?string $notes = null): ComponentInstallation
    {
        return DB::transaction(function () use ($installation, $date, $notes) {
            $locked = ComponentInstallation::whereKey($installation->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->removed_date !== null) {
                throw ValidationException::withMessages([
                    'installation' => 'Komponen ini sudah dilepas sebelumnya.',
                ]);
            }

            if ($date->lt($locked->installed_date)) {
                throw ValidationException::withMessages([
                    'removed_date' => 'Tanggal lepas tidak boleh sebelum tanggal pasang.',
                ]);
            }

            $locked->update([
                'removed_date' => $date,
                'notes' => $this->mergeNotes($locked->notes, $notes),
            ]);

            $locked->component()->update(['status' => ComponentStatus::InStock]);

            return $locked->fresh();
        });
    }

    /**
     * Pindahkan komponen ke host lain = Lepas + Pasang dalam satu transaksi,
     * menghasilkan dua baris riwayat.
     *
     * @throws ValidationException bila komponen tidak sedang terpasang atau
     *                             tujuan sama dengan host saat ini
     */
    public function move(Component $component, Asset $target, CarbonInterface $date, ?string $notes = null): ComponentInstallation
    {
        return DB::transaction(function () use ($component, $target, $date, $notes) {
            $current = ComponentInstallation::where('component_id', $component->getKey())
                ->whereNull('removed_date')
                ->lockForUpdate()
                ->first();

            if (! $current) {
                throw ValidationException::withMessages([
                    'component_id' => 'Komponen ini tidak sedang terpasang di host mana pun. Gunakan fitur Pasang.',
                ]);
            }

            if ($current->asset_id === $target->getKey()) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Komponen sudah terpasang di host tersebut.',
                ]);
            }

            if ($date->lt($current->installed_date)) {
                throw ValidationException::withMessages([
                    'move_date' => 'Tanggal pindah tidak boleh sebelum tanggal pasang sebelumnya.',
                ]);
            }

            $this->remove($current, $date, 'Dipindahkan ke host lain');

            return $this->install($component, $target, $date, $notes);
        });
    }

    /**
     * Gabungkan catatan lama dengan baru tanpa menghilangkan yang lama.
     */
    private function mergeNotes(?string $existing, ?string $incoming): ?string
    {
        if ($incoming === null || trim($incoming) === '') {
            return $existing;
        }

        return trim(($existing ? $existing."\n" : '').trim($incoming));
    }
}
