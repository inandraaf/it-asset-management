<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentInstallation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComponentInstallation>
 */
class ComponentInstallationFactory extends Factory
{
    protected $model = ComponentInstallation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'component_id' => Component::factory(),
            'asset_id' => Asset::factory(),
            'installed_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'removed_date' => null,
            'notes' => fake()->optional()->sentence(),
            'installed_by' => User::factory(),
        ];
    }

    /**
     * Pemasangan yang sudah selesai (dilepas).
     */
    public function removed(): static
    {
        return $this->state(function (array $attributes) {
            $installed = \Illuminate\Support\Carbon::parse($attributes['installed_date']);

            return [
                'removed_date' => $installed->copy()
                    ->addDays(random_int(1, 120))
                    ->min(now())
                    ->format('Y-m-d'),
            ];
        });
    }
}
