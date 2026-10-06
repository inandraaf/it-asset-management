<?php

namespace Tests\Unit;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Services\AssetCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * @see dokumentasi/06-manajemen-aset.md §2
 */
class AssetCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function generator(): AssetCodeGenerator
    {
        return app(AssetCodeGenerator::class);
    }

    public function test_first_pc_code_starts_at_one(): void
    {
        $code = DB::transaction(fn () => $this->generator()->next(AssetType::PC));

        $this->assertSame('PC-'.now()->year.'-0001', $code);
    }

    public function test_first_laptop_code_uses_lt_prefix(): void
    {
        $code = DB::transaction(fn () => $this->generator()->next(AssetType::Laptop));

        $this->assertSame('LT-'.now()->year.'-0001', $code);
    }

    /**
     * Kode berikutnya dihitung dari aset yang SUDAH tersimpan. Memanggil
     * next() dua kali tanpa menyimpan aset menghasilkan kode yang sama —
     * itulah sebabnya pembuatan aset wajib menyimpan di dalam transaksi
     * yang sama (lihat AssetController::store).
     */
    public function test_sequence_increments_only_after_asset_is_persisted(): void
    {
        $generate = function (string $mac): string {
            return DB::transaction(function () use ($mac) {
                $code = $this->generator()->next(AssetType::PC);

                Asset::create([
                    'asset_code' => $code,
                    'type' => AssetType::PC,
                    'brand' => 'Dell',
                    'mac_address' => $mac,
                    'specs' => ['cpu' => 'i5'],
                ]);

                return $code;
            });
        };

        $first = $generate('AA:BB:CC:DD:EE:01');
        $second = $generate('AA:BB:CC:DD:EE:02');

        $this->assertSame('PC-'.now()->year.'-0001', $first);
        $this->assertSame('PC-'.now()->year.'-0002', $second);
    }

    public function test_same_code_is_returned_when_nothing_is_persisted(): void
    {
        $codes = DB::transaction(function () {
            $generator = $this->generator();

            return [$generator->next(AssetType::PC), $generator->next(AssetType::PC)];
        });

        $this->assertSame(['PC-'.now()->year.'-0001', 'PC-'.now()->year.'-0001'], $codes);
    }

    public function test_type_prefixes_do_not_interfere(): void
    {
        $codes = DB::transaction(function () {
            $generator = $this->generator();

            $codes = [];

            foreach ([AssetType::PC, AssetType::Laptop, AssetType::PC] as $index => $type) {
                $code = $generator->next($type);

                Asset::create([
                    'asset_code' => $code,
                    'type' => $type,
                    'brand' => 'Dell',
                    'mac_address' => sprintf('AA:BB:CC:DD:EE:%02d', $index + 1),
                    'specs' => ['cpu' => 'i5'],
                ]);

                $codes[] = $code;
            }

            return $codes;
        });

        $this->assertSame([
            'PC-'.now()->year.'-0001',
            'LT-'.now()->year.'-0001',
            'PC-'.now()->year.'-0002',
        ], $codes);
    }

    public function test_sequence_accounts_for_soft_deleted_assets(): void
    {
        Asset::factory()->pc()->create(['asset_code' => 'PC-'.now()->year.'-0007']);
        Asset::factory()->pc()->create(['asset_code' => 'PC-'.now()->year.'-0008'])->delete();

        $code = DB::transaction(fn () => $this->generator()->next(AssetType::PC));

        // Nomor terakhir dihitung termasuk aset yang sudah di-soft delete.
        $this->assertSame('PC-'.now()->year.'-0009', $code);
    }

    public function test_sequence_handles_multi_digit_numbers(): void
    {
        Asset::factory()->pc()->create(['asset_code' => 'PC-'.now()->year.'-0099']);
        Asset::factory()->pc()->create(['asset_code' => 'PC-'.now()->year.'-0100']);

        $code = DB::transaction(fn () => $this->generator()->next(AssetType::PC));

        $this->assertSame('PC-'.now()->year.'-0101', $code);
    }

    /**
     * Secara teks, "9999" > "10000", sehingga pengurutan leksikografis akan
     * memilih baris yang salah begitu urutan melewati 4 digit — generator
     * akan terus menerbitkan kode yang sudah terpakai.
     */
    public function test_sequence_survives_passing_four_digits(): void
    {
        Asset::factory()->pc()->create(['asset_code' => 'PC-'.now()->year.'-9999']);
        Asset::factory()->pc()->create(['asset_code' => 'PC-'.now()->year.'-10000']);

        $code = DB::transaction(fn () => $this->generator()->next(AssetType::PC));

        $this->assertSame('PC-'.now()->year.'-10001', $code);
    }

    public function test_generated_codes_are_sequential_when_used_to_create_assets(): void
    {
        DB::transaction(function () {
            $generator = $this->generator();

            Asset::create([
                'asset_code' => $generator->next(AssetType::PC),
                'type' => AssetType::PC,
                'brand' => 'Dell',
                'mac_address' => 'AA:BB:CC:DD:EE:01',
                'specs' => ['cpu' => 'i5'],
            ]);
        });

        DB::transaction(function () {
            $generator = $this->generator();

            Asset::create([
                'asset_code' => $generator->next(AssetType::PC),
                'type' => AssetType::PC,
                'brand' => 'Dell',
                'mac_address' => 'AA:BB:CC:DD:EE:02',
                'specs' => ['cpu' => 'i5'],
            ]);
        });

        $this->assertSame(
            ['PC-'.now()->year.'-0001', 'PC-'.now()->year.'-0002'],
            Asset::orderBy('id')->pluck('asset_code')->all()
        );
    }
}
