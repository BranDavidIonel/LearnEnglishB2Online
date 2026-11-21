<?php

namespace Database\Factories;

use App\Models\LearnerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearnerProfile>
 */
class LearnerProfileFactory extends Factory
{
    /**
     * A new learner: no XP, the default goal and settings.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'xp' => 0,
            'goal' => (int) config('learning.rules.goal'),
            'streak_count' => 0,
            'streak_last' => null,
            'theme' => 'dark',
            'speech_rate' => (float) config('learning.rules.rate.default'),
            'voice' => null,
            'sound' => true,
            'spoken' => 0,
        ];
    }

    /** A streak of $days days, the last one being $last (Y-m-d). */
    public function streak(int $days, string $last): static
    {
        return $this->state(['streak_count' => $days, 'streak_last' => $last]);
    }
}
