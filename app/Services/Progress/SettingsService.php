<?php

namespace App\Services\Progress;

use App\Enums\Theme;
use App\Models\User;

/**
 * The learner's preferences: colour theme, speech rate and voice, sound effects, the
 * daily XP goal and the on/off flags of the pages (e.g. "keepSkippedWords": the learner
 * chose to go through the words of the skipped levels instead of marking them known).
 */
class SettingsService
{
    /** The flags the pages may set. */
    public const array FLAGS = ['keepSkippedWords'];

    public function __construct(private readonly LearnerState $state) {}

    /** @return array{theme: string, rate: float, voice: string|null, sound: bool} */
    public function settings(User $user): array
    {
        $profile = $this->state->profile($user);

        return [
            'theme' => $profile->theme->value,
            'rate' => $profile->speech_rate,
            'voice' => $profile->voice,
            'sound' => $profile->sound,
        ];
    }

    /**
     * Changes some of the settings (the keys that are present).
     *
     * @param  array{theme?: string, rate?: float|int, voice?: string|null, sound?: bool}  $patch
     * @return array{theme: string, rate: float, voice: string|null, sound: bool}
     */
    public function update(User $user, array $patch): array
    {
        $profile = $this->state->profile($user);

        if (array_key_exists('theme', $patch)) {
            $profile->theme = Theme::from($patch['theme']);
        }

        if (array_key_exists('rate', $patch)) {
            $profile->speech_rate = (float) $patch['rate'];
        }

        if (array_key_exists('voice', $patch)) {
            $profile->voice = $patch['voice'];
        }

        if (array_key_exists('sound', $patch)) {
            $profile->sound = $patch['sound'];
        }

        $profile->save();

        return $this->settings($user);
    }

    public function goal(User $user): int
    {
        return $this->state->profile($user)->goal;
    }

    public function setGoal(User $user, int $goal): void
    {
        $profile = $this->state->profile($user);
        $profile->goal = $goal;
        $profile->save();
    }

    /** @return array<string, bool> */
    public function flags(User $user): array
    {
        return array_map(fn (mixed $value): bool => (bool) $value, $this->state->profile($user)->flags ?? []);
    }

    public function setFlag(User $user, string $flag, bool $value): void
    {
        $profile = $this->state->profile($user);
        $profile->flags = [...($profile->flags ?? []), $flag => $value];
        $profile->save();
    }
}
