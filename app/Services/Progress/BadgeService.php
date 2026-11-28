<?php

namespace App\Services\Progress;

use App\Models\UnitProgress;
use App\Models\User;
use App\Services\Content\ContentRepository;

/**
 * The badges of the progress page (original badges): which milestones the learner has
 * reached. The names and descriptions are interface texts, translated here.
 */
class BadgeService
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly LearnerState $state,
        private readonly CourseProgressService $course,
        private readonly WordReviewService $words,
        private readonly VerbReviewService $verbs,
    ) {}

    /** @return list<array{icon: string, name: string, desc: string, ok: bool}> */
    public function badges(User $user): array
    {
        $profile = $this->state->profile($user);
        $passedUnits = $this->state->units($user)->filter(fn (UnitProgress $unit): bool => $unit->passed)->count();
        $talks = $user->speakingScores()->count();
        $words = $this->words->counts($user)['learned'];
        $verbs = $this->verbs->counts($user)['learned'];
        $verbTotal = count($this->content->verbs());
        // The badges count the streak as stored, even after a break (like the original).
        $streak = $profile->streak_count;

        return array_values(array_map(fn (array $badge): array => [
            'icon' => $badge[0],
            'name' => (string) __($badge[1]),
            'desc' => (string) __($badge[2]),
            'ok' => $badge[3],
        ], [
            ['🚀', 'First step', 'Earn your first XP', $profile->xp > 0],
            ['🧭', 'I know where I start', 'Take the placement test', $this->state->placement($user) !== null],
            ['📗', 'First unit', 'Pass a unit test', $passedUnits >= 1],
            ['📚', 'Ten units', 'Pass 10 units', $passedUnits >= 10],
            ['🗣️', 'First conversation', 'Finish a conversation in Speaking', $talks >= 1],
            ['🎙️', '100 sentences', 'Say 100 sentences correctly', $profile->spoken >= 100],
            ['🔥', '3 days in a row', 'Study 3 days in a row', $streak >= 3],
            ['⚡', 'One week', 'Study 7 days in a row', $streak >= 7],
            ['🏅', 'One month', 'Study 30 days in a row', $streak >= 30],
            ['🗂️', '50 words', 'Learn 50 words', $words >= 50],
            ['🧠', '200 words', 'Learn 200 words', $words >= 200],
            ['📘', '750 words', 'An A1-sized vocabulary', $words >= 750],
            ['📙', '1500 words', 'An A2-sized vocabulary', $words >= 1500],
            ['📕', '3000 words', 'A B1-sized vocabulary', $words >= 3000],
            ['📚', '4500 words', 'A B2-sized vocabulary', $words >= 4500],
            ['🔁', '25 irregular verbs', 'Learn the forms of 25 irregular verbs', $verbs >= 25],
            ['🏋️', '100 irregular verbs', 'Learn the forms of 100 irregular verbs', $verbs >= 100],
            ['👑', 'Every irregular verb', 'Learn the forms of every irregular verb in the list', $verbTotal > 0 && $verbs >= $verbTotal],
            ['💎', '1000 XP', 'Collect 1000 XP', $profile->xp >= 1000],
            ['🥉', 'Level A1', 'Finish level A1', $this->levelFinished($user, 'a1')],
            ['🥈', 'Level A2', 'Finish level A2', $this->levelFinished($user, 'a2')],
            ['🥇', 'Level B1', 'Finish level B1', $this->levelFinished($user, 'b1')],
            ['🏆', 'Level B2', 'Finish level B2', $this->levelFinished($user, 'b2')],
        ]));
    }

    private function levelFinished(User $user, string $levelId): bool
    {
        return $this->content->level($levelId) !== null && $this->course->levelDone($user, $levelId);
    }
}
