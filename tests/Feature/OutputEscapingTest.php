<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: data yang diketik pengguna tidak boleh dipakai sebagai kode.
 *
 * Nama departemen/karyawan sebelumnya diinterpolasi langsung ke string
 * JavaScript di atribut `onsubmit`, sehingga nama berisi tanda kutip bisa
 * keluar dari string dan mengeksekusi script (stored XSS).
 * Sekarang nama hanya diteruskan lewat atribut data-*, bukan kode.
 *
 * @see dokumentasi/11-non-fungsional.md §4
 */
class OutputEscapingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_department_name_cannot_break_out_of_inline_script(): void
    {
        Department::create(['nama_dept' => "RnD');alert(1);//"]);

        $response = $this->actingAs($this->admin())->get(route('departments.index'));

        $response->assertOk();

        $html = $response->getContent();

        // Tidak boleh ada kode JS yang mengandung muatan penyerang.
        $this->assertStringNotContainsString('alert(1)', $this->inlineHandlers($html));
    }

    public function test_employee_name_cannot_break_out_of_inline_script(): void
    {
        $employee = Employee::factory()->create(['nama' => "Budi');alert(1);//"]);

        $response = $this->actingAs($this->admin())->get(route('employees.index'));

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringNotContainsString('alert(1)', $this->inlineHandlers($html));
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
    }

    public function test_department_name_is_still_shown_in_the_confirm_dialog_data(): void
    {
        Department::create(['nama_dept' => 'RnD']);

        $this->actingAs($this->admin())
            ->get(route('departments.index'))
            ->assertOk()
            // Sejak R6 konfirmasi memakai SweetAlert2 lewat atribut data-confirm.
            ->assertSee('data-confirm="Hapus departemen RnD?"', false)
            ->assertSee('data-confirm-button="Hapus Departemen"', false);
    }

    /**
     * Pesan konfirmasi kini berada di atribut HTML `data-confirm`. Kutip ganda
     * pada nama harus tetap ter-escape agar tidak bisa keluar dari atribut.
     */
    public function test_department_name_cannot_break_out_of_data_confirm_attribute(): void
    {
        Department::create(['nama_dept' => 'RnD" onmouseover="alert(1)']);

        $html = $this->actingAs($this->admin())
            ->get(route('departments.index'))
            ->assertOk()
            ->getContent();

        // Kutip mentah tidak boleh muncul di dalam atribut data-confirm.
        $this->assertStringNotContainsString('data-confirm="Hapus departemen RnD" onmouseover=', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    /**
     * Regression kedua: nilai yang di-flash ke `old()` dipakai sebagai nilai
     * awal Alpine di atribut `x-data`.
     *
     * Blade meng-escape `'` menjadi `&#039;`, tetapi HTML parser men-decode
     * entity tersebut SEBELUM Alpine mengevaluasi atribut sebagai JavaScript.
     * Jadi `x-data="{ mac: '{{ old(...) }}' }"` dapat ditembus. Perbaikannya
     * memakai @js() yang meng-encode apostrof sebagai \u0027 sehingga string
     * tidak dapat ditutup.
     */
    public function test_old_mac_cannot_break_out_of_alpine_x_data(): void
    {
        $payload = "x');alert(1);//";

        // Kirim MAC tidak valid -> validasi gagal -> nilai lama ter-flash.
        $this->actingAs($this->admin())
            ->post(route('assets.store'), [
                'type' => 'PC',
                'brand' => 'Dell',
                'mac_address' => $payload,
                'specs' => ['cpu' => 'i5'],
            ])
            ->assertSessionHasErrors('mac_address');

        $html = $this->actingAs($this->admin())
            ->get(route('assets.create'))
            ->assertOk()
            ->getContent();

        $xData = $this->alpineData($html);

        // Muatan mentah tidak boleh muncul di dalam kode Alpine.
        $this->assertStringNotContainsString("');alert(1)", $xData, 'Payload keluar dari string JavaScript di x-data.');

        // Apostrof harus ter-escape sebagai \u0027 di dalam string.
        $this->assertStringContainsString('\u0027', $xData, 'Nilai tidak di-encode @js().');
    }

    public function test_edit_page_mac_cannot_break_out_of_alpine_x_data(): void
    {
        $asset = Asset::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:01']);

        $this->actingAs($this->admin())
            ->put(route('assets.update', $asset), [
                'brand' => $asset->brand,
                'mac_address' => "y');alert(1);//",
                'ip_address' => null,
                'specs' => ['cpu' => 'i5'],
                'status' => $asset->status->value,
            ])
            ->assertSessionHasErrors('mac_address');

        $xData = $this->alpineData(
            $this->actingAs($this->admin())
                ->get(route('assets.edit', $asset))
                ->assertOk()
                ->getContent()
        );

        $this->assertStringNotContainsString("');alert(1)", $xData, 'Payload keluar dari string JavaScript di x-data (halaman edit).');
        $this->assertStringContainsString('\u0027', $xData, 'Nilai tidak di-encode @js().');
    }

    public function test_alpine_initial_values_still_work_for_normal_input(): void
    {
        $this->actingAs($this->admin())
            ->get(route('assets.create'))
            ->assertOk()
            ->assertSee("type: 'PC'", false);
    }

    /**
     * Ambil isi atribut event handler inline (on*) dari HTML,
     * yaitu bagian yang dieksekusi sebagai JavaScript.
     */
    private function inlineHandlers(string $html): string
    {
        preg_match_all('/\son[a-z]+\s*=\s*"[^"]*"/i', $html, $matches);

        return implode("\n", $matches[0]);
    }

    /**
     * Ambil isi atribut x-data — bagian yang dievaluasi Alpine sebagai JavaScript.
     */
    private function alpineData(string $html): string
    {
        preg_match_all('/x-data\s*=\s*"(.*?)"/is', $html, $matches);

        return implode("\n", $matches[1]);
    }
}
