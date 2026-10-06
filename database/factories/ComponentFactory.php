<?php

namespace Database\Factories;

use App\Enums\ComponentCategory;
use App\Enums\ComponentStatus;
use App\Models\Component;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Component>
 */
class ComponentFactory extends Factory
{
    protected $model = Component::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(ComponentCategory::cases());

        return [
            'component_code' => $category->codePrefix().'-'.now()->year.'-'
                .str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'category' => $category,
            'brand' => fake()->randomElement(['Kingston', 'Corsair', 'Samsung', 'Seagate', 'Intel', 'AMD', 'Logitech']),
            'model' => strtoupper(fake()->bothify('Model-####')),
            'serial_number' => strtoupper(fake()->unique()->bothify('SN##########')),
            'specs' => $this->specsFor($category),
            'status' => ComponentStatus::InStock,
            'notes' => null,
            'created_by' => null,
        ];
    }

    /**
     * Spesifikasi contoh sesuai kategori.
     *
     * @return array<string, string>
     */
    private function specsFor(ComponentCategory $category): array
    {
        return match ($category) {
            ComponentCategory::Cpu => ['socket' => 'LGA1200', 'cores' => '6', 'threads' => '12'],
            ComponentCategory::Ram => ['capacity' => '8GB', 'type' => 'DDR4', 'speed' => '3200MHz'],
            ComponentCategory::Storage => ['capacity' => '512GB', 'type' => 'SSD', 'interface' => 'NVMe'],
            ComponentCategory::Gpu => ['memory' => '4GB', 'memory_type' => 'GDDR6'],
            ComponentCategory::Motherboard => ['socket' => 'LGA1200', 'form_factor' => 'micro-ATX'],
            ComponentCategory::Psu => ['wattage' => '500W', 'efficiency' => '80+ Bronze'],
            ComponentCategory::Casing => ['form_factor' => 'ATX Mid Tower'],
            ComponentCategory::Monitor => ['size' => '24"', 'resolution' => '1920x1080'],
            ComponentCategory::Keyboard => ['connection' => 'USB'],
            ComponentCategory::Mouse => ['connection' => 'USB', 'dpi' => '1000'],
            ComponentCategory::Other => [],
        };
    }

    public function ofCategory(ComponentCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
            'component_code' => $category->codePrefix().'-'.now()->year.'-'
                .str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'specs' => $this->specsFor($category),
        ]);
    }

    public function withoutSerial(): static
    {
        return $this->state(fn (array $attributes) => [
            'serial_number' => null,
        ]);
    }
}
