<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WordReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WordReview>
 */
class WordReviewFactory extends Factory
{
    /**
     * A word just put into the deck: box 0, due today.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'word' => fake()->unique()->word(),
            'box' => 0,
            'due_on' => now()->toDateString(),
            'ok' => 0,
            'bad' => 0,
            'known' => false,
        ];
    }

    /** A learned word (box 3), next review in a week. */
    public function learned(): static
    {
        return $this->state(['box' => 3, 'due_on' => now()->addDays(7)->toDateString(), 'ok' => 3]);
    }
}
