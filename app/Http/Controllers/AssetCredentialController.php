<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssetCredentialRequest;
use App\Models\Asset;
use App\Models\AssetCredential;
use Illuminate\Http\RedirectResponse;

/**
 * Kredensial akses per aset — dapat lebih dari satu (S5).
 *
 * Hanya Admin IT (dijaga middleware `role:admin` pada route).
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md S5
 */
class AssetCredentialController extends Controller
{
    public function store(AssetCredentialRequest $request, Asset $asset): RedirectResponse
    {
        $asset->credentials()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', 'Kredensial "'.$request->input('label').'" berhasil ditambahkan.');
    }

    public function update(AssetCredentialRequest $request, AssetCredential $credential): RedirectResponse
    {
        $data = $request->validated();

        // Kata sandi kosong = jangan ubah nilai lama.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $credential->update($data);

        return redirect()
            ->route('assets.show', $credential->asset)
            ->with('success', 'Kredensial "'.$credential->label.'" berhasil diperbarui.');
    }

    public function destroy(AssetCredential $credential): RedirectResponse
    {
        $asset = $credential->asset;
        $label = $credential->label;

        $credential->delete();

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', 'Kredensial "'.$label.'" berhasil dihapus.');
    }
}
