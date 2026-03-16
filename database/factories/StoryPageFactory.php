<?php

namespace Database\Factories;

use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StoryPage>
 */
class StoryPageFactory extends Factory
{
    private static array $counters = [];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'page_number' => fn (array $attributes) => $this->nextPageNumber($attributes['story_id']),
            'content' => fake()->paragraph(),
            'illustration_prompt' => fake()->optional(0.8)->sentence(),
            'image_url' => null,
        ];
    }

    /**
     * Get the next sequential page number for a given story.
     */
    private function nextPageNumber(mixed $storyId): int
    {
        $key = is_object($storyId) ? spl_object_id($storyId) : (string) $storyId;

        if (! isset(self::$counters[$key])) {
            self::$counters[$key] = 0;
        }

        return ++self::$counters[$key];
    }

    /**
     * State: page with a generated image URL.
     */
    public function withImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'illustration_prompt' => $attributes['illustration_prompt'] ?? fake()->sentence(),
            'image_url' => fake()->imageUrl(1024, 768),
        ]);
    }
}
