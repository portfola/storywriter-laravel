<?php

namespace Database\Factories;

use App\Models\TogetherAiUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TogetherAiUsage>
 */
class TogetherAiUsageFactory extends Factory
{
    protected $model = TogetherAiUsage::class;

    public function definition(): array
    {
        $serviceType = $this->faker->randomElement([
            TogetherAiUsage::SERVICE_STORY,
            TogetherAiUsage::SERVICE_IMAGE,
        ]);

        return [
            'user_id' => User::factory(),
            'service_type' => $serviceType,
            'model_id' => 'test-model',
            'estimated_cost' => $this->faker->randomFloat(4, 0.001, 0.01),
        ];
    }
}
