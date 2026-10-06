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
        $type = fake()->randomElement(AssetType::cases());

        return [
            'asset_code' => $type->codePrefix().'-'.now()->year.'-'
                .str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'type' => $type,
            'brand' => fake()->randomElement(['Dell', 'Lenovo', 'HP', 'Asus', 'Acer', 'Apple']),
            'hostname' => strtoupper(fake()->unique()->bothify('PC-????-##')),
            'mac_address' => $this->randomMacAddress(),
            'ip_address' => null,
            'specs' => [
                'cpu' => fake()->randomElement(['i3-10100', 'i5-10400', 'i7-10700', 'Ryzen 5 5600']),
                'ram' => fake()->randomElement(['8GB', '16GB', '32GB']),
                'storage' => fake()->randomElement(['256GB SSD', '512GB SSD', '1TB HDD']),
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
