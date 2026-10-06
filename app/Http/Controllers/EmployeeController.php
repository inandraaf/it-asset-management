<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CRUD master karyawan.
 *
 * @see dokumentasi/05-master-data.md §2
 */
class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $employees = Employee::query()
            ->with('department')
            ->withCount('activeAssignments')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($w) => $w
                    ->where('nama', 'ilike', $term)
                    ->orWhere('nip', 'ilike', $term));
            })
            ->when($request->filled('department_id'), fn ($query) => $query
                ->where('department_id', $request->integer('department_id')))
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        $departments = Department::orderBy('nama_dept')->get();

        return view('employees.index', compact('employees', 'departments'));
    }

    public function create(): View
    {
        $departments = Department::orderBy('nama_dept')->get();

        return view('employees.create', compact('departments'));
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        Employee::create($request->validated());

        return redirect()
            ->route('employees.index')
            ->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function show(Employee $employee): View
    {
        $employee->load('department');

        // Aset aktif: paling banyak satu baris (partial unique index), jadi
        // query kecil. Riwayat dipaginasi agar tidak memuat seluruh baris
        // untuk karyawan berusia kerja panjang.
        $activeAssignments = $employee->activeAssignments()
            ->with('asset')
            ->orderByDesc('assigned_date')
            ->get();

        $history = $employee->assignments()
            ->with('asset')
            ->orderByDesc('assigned_date')
            ->paginate(25)
            ->withQueryString();

        return view('employees.show', compact('employee', 'activeAssignments', 'history'));
    }

    public function edit(Employee $employee): View
    {
        $departments = Department::orderBy('nama_dept')->get();

        return view('employees.edit', compact('employee', 'departments'));
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        return redirect()
            ->route('employees.index')
            ->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        if ($employee->hasActiveAssets()) {
            return redirect()
                ->route('employees.index')
                ->with('error', 'Karyawan masih memegang aset, tarik aset terlebih dahulu.');
        }

        // Karyawan dengan riwayat aset tidak dihapus agar audit trail utuh
        // (FK asset_assignments.employee_id memakai restrictOnDelete).
        if ($employee->assignments()->exists()) {
            return redirect()
                ->route('employees.index')
                ->with('error', 'Karyawan memiliki riwayat pemakaian aset sehingga tidak dapat dihapus. Riwayat harus dipertahankan untuk audit.');
        }

        $employee->delete();

        return redirect()
            ->route('employees.index')
            ->with('success', 'Karyawan berhasil dihapus.');
    }
}
