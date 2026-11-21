<?php

namespace App\Services\Progress;

use App\Enums\Level;
use App\Models\User;

/**
 * The few numbers every page shows (the "learner" shared prop, docs/architecture.md §4):
 * total XP, XP today, the daily goal, the streak, the level being worked on and the three
 * menu badges (words and verbs due today, mistakes to practise).
 */
class LearnerSummary
{
    public function __construct(
        private readonly LearnerState $state,
        private readonly XpService $xp,
        private readonly CourseProgressService $course,
        private readonly WordReviewService $words,
        private readonly VerbReviewService $verbs,
        private readonly MistakeService $mistakes,
    ) {}

    /**
     * @return array{xp: int, xpToday: int, goal: int, streak: int, level: array{id: string, name: string, color: string}|null, due: array{words: int, verbs: int, mistakes: int}}
     */
    public function for(User $user): array
    {
        $level = Level::tryFrom((string) ($this->course->currentLevel($user)['id'] ?? ''));

        return [
            'xp' => $this->xp->total($user),
            'xpToday' => $this->xp->today($user),
            'goal' => $this->state->profile($user)->goal,
            'streak' => $this->xp->streak($user),
            'level' => $level === null ? null : ['id' => $level->value, 'name' => $level->label(), 'color' => $level->color()],
            'due' => [
                'words' => $this->words->dueCount($user),
                'verbs' => $this->verbs->dueCount($user),
                'mistakes' => $this->mistakes->count($user),
            ],
        ];
    }
}
