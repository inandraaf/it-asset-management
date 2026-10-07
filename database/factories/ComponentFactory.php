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
            ComponentCategory::Cpu => ['series' => 'i7-11700', 'cores' => '8'],
            ComponentCategory::Ram => ['capacity' => '8GB', 'type' => 'DDR4'],
            ComponentCategory::Storage => ['capacity' => '512GB', 'type' => 'SSD'],
            ComponentCategory::Gpu => ['model' => 'RTX 3060', 'memory' => '12GB'],
            ComponentCategory::Motherboard => ['chipset' => 'H510'],
            ComponentCategory::Psu => ['wattage' => '500W'],
            ComponentCategory::Casing => ['form_factor' => 'micro-ATX'],
            ComponentCategory::Monitor => ['size' => '24"'],
            ComponentCategory::Keyboard => ['connection' => 'USB'],
            ComponentCategory::Mouse => ['connection' => 'USB'],
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
