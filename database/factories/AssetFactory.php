<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Default PC (deterministik). Tipe lain dipilih eksplisit lewat state
        // laptop()/cctv()/printer(), sehingga test tidak bergantung kebetulan.
        $type = AssetType::PC;

        return [
            'asset_code' => $type->codePrefix().'-'.now()->year.'-'
                .str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'type' => $type,
            'brand' => fake()->randomElement(['Dell', 'Lenovo', 'HP', 'Asus', 'Acer', 'Apple']),
            // Hostname disimpan lowercase, konsisten dengan normalisasi
            // StoreAssetRequest dan seeder.
            'hostname' => fake()->unique()->bothify('pc-????-##'),
            'mac_address' => $this->randomMacAddress(),
            'ip_address' => null,
            // Fase 2: `specs` host hanya menyimpan atribut non-fisik (OS).
            // Komponen fisik dikelola lewat tabel `components`.
            'specs' => [
                'os' => fake()->randomElement(['Windows 10', 'Windows 11', 'Ubuntu 22.04']),
            ],
            'status' => AssetStatus::Available,
            'created_by' => null,
        ];
    }

    /**
     * Aset tanpa hostname (hostname opsional).
     */
    public function withoutHostname(): static
    {
        return $this->state(fn (array $attributes) => [
            'hostname' => null,
        ]);
    }

    public function pc(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AssetType::PC,
            'asset_code' => 'PC-'.now()->year.'-'
                .str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
        ]);
    }

    public function laptop(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AssetType::Laptop,
            'asset_code' => 'LT-'.now()->year.'-'
                .str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
        ]);
    }

    public function cctv(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AssetType::Cctv,
            // CCTV: MAC opsional, tanpa pemilik.
            'mac_address' => null,
            'hostname' => fake()->unique()->bothify('cctv-????-##'),
            'asset_code' => 'CCTV-'.now()->year.'-'
                .str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
        ]);
    }

    public function printer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AssetType::Printer,
            // Printer: tanpa MAC.
            'mac_address' => null,
            'hostname' => fake()->unique()->bothify('prn-????-##'),
            'asset_code' => 'PRN-'.now()->year.'-'
                .str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
        ]);
    }

    public function withIp(?string $ip = null): static
    {
        return $this->state(fn (array $attributes) => [
            'ip_address' => $ip ?? fake()->unique()->ipv4(),
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssetStatus::Retired,
        ]);
    }

    public function inRepair(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssetStatus::InRepair,
        ]);
    }

    protected function randomMacAddress(): string
    {
        return sprintf(
            '%02X:%02X:%02X:%02X:%02X:%02X',
            ...array_map(fn () => random_int(0, 255), range(1, 6))
        );
    }
}
