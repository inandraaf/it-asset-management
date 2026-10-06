<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssetAssignment>
 */
class AssetAssignmentFactory extends Factory
{
    protected $model = AssetAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'employee_id' => Employee::factory(),
            'assigned_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'returned_date' => null,
            'notes' => fake()->optional()->sentence(),
            'assigned_by' => User::factory(),
        ];
    }

    public function returned(): static
    {
        return $this->state(function (array $attributes) {
            $assigned = \Illuminate\Support\Carbon::parse($attributes['assigned_date']);

            return [
                'returned_date' => $assigned->copy()
                    ->addDays(random_int(1, 180))
                    ->min(now())
                    ->format('Y-m-d'),
            ];
        });
    }
}
