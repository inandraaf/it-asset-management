<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UserRequest;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Manajemen akun user (hanya Admin IT).
 *
 * Admin IT adalah superadmin yang dapat menambah user read-only, sehingga
 * bila ia tidak tersedia, tim EDP lain tetap bisa memantau aset.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md S3
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($w) => $w
                    ->where('name', 'ilike', $term)
                    ->orWhere('username', 'ilike', $term));
            })
            ->when($request->filled('role'), fn ($query) => $query
                ->where('role', $request->string('role')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => UserRole::options(),
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'roles' => UserRole::options(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // 'role' tidak mass-assignable pada model User; set eksplisit.
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole(UserRole::from($data['role']));

        return redirect()
            ->route('users.index')
            ->with('success', 'Akun '.$user->username.' berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user,
            'roles' => UserRole::options(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
        ]);

        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->assignRole(UserRole::from($data['role']));

        return redirect()
            ->route('users.index')
            ->with('success', 'Akun '.$user->username.' berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Jangan hapus akun sendiri.
        if ($request->user()->is($user)) {
            return redirect()
                ->route('users.index')
                ->with('error', 'Anda tidak dapat menghapus akun yang sedang dipakai.');
        }

        // Jangan sampai tidak ada Admin IT tersisa.
        if ($user->isAdmin() && User::where('role', UserRole::Admin)->count() <= 1) {
            return redirect()
                ->route('users.index')
                ->with('error', 'Minimal harus ada satu Admin IT. Tambahkan admin lain sebelum menghapus akun ini.');
        }

        $username = $user->username;

        // Putuskan relasi audit agar user lain tetap dapat dihapus.
        Asset::where('created_by', $user->id)->update(['created_by' => null]);
        AssetAssignment::where('assigned_by', $user->id)->update(['assigned_by' => null]);

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'Akun '.$username.' berhasil dihapus.');
    }
}
