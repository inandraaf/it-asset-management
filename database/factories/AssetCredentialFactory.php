<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssetCredential>
 */
class AssetCredentialFactory extends Factory
{
    protected $model = AssetCredential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'label' => fake()->randomElement(['Admin', 'User Biasa', 'VNC', 'Service']),
            'username' => fake()->optional()->userName(),
            'password' => fake()->optional()->password(8, 16),
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
