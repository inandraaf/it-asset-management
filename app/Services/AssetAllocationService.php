<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logika alokasi aset: assign, return, dan transfer.
 *
 * Semua operasi dibungkus transaksi dan row lock agar invariant
 * "satu assignment aktif per aset" tetap terjaga walau ada request bersamaan.
 *
 * @see dokumentasi/07-alokasi-aset.md §6
 */
class AssetAllocationService
{
    /**
     * Tugaskan aset kepada karyawan.
     *
     * @throws ValidationException bila aset tidak berstatus Available
     *                             atau masih dipegang karyawan lain
     */
    public function assign(Asset $asset, int $employeeId, CarbonInterface $date, ?string $notes = null): AssetAssignment
    {
        return DB::transaction(function () use ($asset, $employeeId, $date, $notes) {
            $locked = Asset::whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            // Hanya PC/Laptop yang dapat ditugaskan ke karyawan.
            // Printer melekat departemen; CCTV tanpa pemilik.
            if (! $locked->type->isAssignable()) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Aset berjenis '.$locked->type->label().' tidak dapat ditugaskan ke karyawan.',
                ]);
            }

            if ($locked->status !== AssetStatus::Available) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Aset tidak tersedia untuk diserahkan (status saat ini: '.$locked->status->label().').',
                ]);
            }

            if ($locked->assignments()->whereNull('returned_date')->exists()) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Aset masih terpasang pada karyawan lain. Lakukan Return terlebih dahulu.',
                ]);
            }

            $assignment = $locked->assignments()->create([
                'employee_id' => $employeeId,
                'assigned_date' => $date,
                'notes' => $notes,
                'assigned_by' => auth()->id(),
            ]);

            $locked->update(['status' => AssetStatus::Assigned]);

            return $assignment;
        });
    }

    /**
     * Tarik aset dari karyawan kembali ke IT.
     *
     * @throws ValidationException bila assignment sudah ditutup atau
     *                             tanggal return mendahului tanggal assign
     */
    public function return(AssetAssignment $assignment, CarbonInterface $date, ?string $notes = null): AssetAssignment
    {
        return DB::transaction(function () use ($assignment, $date, $notes) {
            $locked = AssetAssignment::whereKey($assignment->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->returned_date !== null) {
                throw ValidationException::withMessages([
                    'assignment' => 'Aset ini sudah dikembalikan sebelumnya.',
                ]);
            }

            if ($date->lt($locked->assigned_date)) {
                throw ValidationException::withMessages([
                    'returned_date' => 'Tanggal return tidak boleh sebelum tanggal assign.',
                ]);
            }

            $locked->update([
                'returned_date' => $date,
                'notes' => $this->mergeNotes($locked->notes, $notes),
            ]);

            $locked->asset()->update(['status' => AssetStatus::Available]);

            return $locked->fresh();
        });
    }

    /**
     * Transfer = Return + Assign dalam SATU transaksi.
     *
     * Admin cukup satu klik, tetapi tetap menghasilkan dua baris riwayat
     * supaya jejak pemegang sebelumnya tidak hilang.
     *
     * @throws ValidationException bila aset tidak sedang dipegang siapa pun
     *                             atau tujuan sama dengan pemegang saat ini
     */
    public function transfer(Asset $asset, int $newEmployeeId, CarbonInterface $date, ?string $notes = null): AssetAssignment
    {
        return DB::transaction(function () use ($asset, $newEmployeeId, $date, $notes) {
            $current = AssetAssignment::where('asset_id', $asset->getKey())
                ->whereNull('returned_date')
                ->lockForUpdate()
                ->first();

            if (! $current) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Aset ini tidak sedang dipegang siapa pun. Gunakan fitur Assign.',
                ]);
            }

            if ($current->employee_id === $newEmployeeId) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Aset sudah dipegang karyawan tersebut.',
                ]);
            }

            if ($date->lt($current->assigned_date)) {
                throw ValidationException::withMessages([
                    'transfer_date' => 'Tanggal transfer tidak boleh sebelum tanggal assign sebelumnya.',
                ]);
            }

            $this->return($current, $date, 'Ditransfer ke karyawan lain');

            return $this->assign($asset, $newEmployeeId, $date, $notes);
        });
    }

    /**
     * Gabungkan catatan lama dengan catatan baru tanpa menghilangkan yang lama.
     */
    private function mergeNotes(?string $existing, ?string $incoming): ?string
    {
        if ($incoming === null || trim($incoming) === '') {
            return $existing;
        }

        return trim(($existing ? $existing."\n" : '').trim($incoming));
    }
}
