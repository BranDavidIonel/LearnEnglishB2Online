<?php

namespace App\Services\Home;

use App\Enums\Level;
use App\Enums\NextActionKind;
use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\Calendar;
use App\Services\Progress\CourseProgressService;
use App\Services\Progress\LearnerState;
use App\Services\Progress\VerbReviewService;
use App\Services\Progress\WordReviewService;
use App\Services\Progress\XpService;

/**
 * What the home page shows (the original dashboard): the big "what's next" card, the
 * "learn the next words" card, the road to B2 (also on the progress page), the last seven
 * days and the numbers of the shortcuts. Every rule comes from the progress services;
 * this class only gathers their answers in the shape the page draws.
 *
 * @phpstan-type LevelTag array{id: string, name: string, color: string}
 * @phpstan-type Counts array{learned: int, learning: int, new: int, total: int}
 */
class DashboardService
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly LearnerState $state,
        private readonly CourseProgressService $course,
        private readonly WordReviewService $words,
        private readonly VerbReviewService $verbs,
        private readonly XpService $xp,
    ) {}

    /**
     * The big card. "welcome" for a learner who has done nothing yet (no placement test,
     * no XP, no exam): the original then offered the placement test or the first unit.
     * Otherwise the next action of the course: a unit step (review first), the level
     * exam, or "done".
     *
     * @return array{kind: string, level: LevelTag|null, unit: array{id: string, order: int|null, title: string|null, titleRo: string|null, emoji: string|null}|null, step: string|null, review: bool}
     */
    public function nextStep(User $user): array
    {
        $action = $this->course->nextAction($user);
        $unit = $action['unit'] === null ? null : $this->content->unitOutline($action['unit']);

        return [
            'kind' => $this->isNewcomer($user) ? 'welcome' : $action['kind']->value,
            'level' => $action['level'] === null ? null : $this->levelTag($action['level']),
            'unit' => $unit === null ? null : [
                'id' => $unit['id'],
                'order' => $unit['order'],
                'title' => $unit['title'],
                'titleRo' => $unit['titleRo'],
                'emoji' => $unit['emoji'],
            ],
            'step' => $action['kind'] === NextActionKind::Unit ? $action['step'] : null,
            'review' => $action['review'],
        ];
    }

    /**
     * "Learn 20 new words": the words still to learn of the earliest level (up to the
     * current one) that has some, measured against the vocabulary of the current level.
     * Null when nothing is left to learn.
     *
     * @return array{level: LevelTag, current: LevelTag, goal: Counts, left: int, batch: int}|null
     */
    public function wordsToLearn(User $user): ?array
    {
        $levelId = $this->words->nextWordsLevel($user);
        $current = $this->course->currentLevel($user);

        if ($levelId === null || $current === null) {
            return null;
        }

        return [
            'level' => $this->levelTag($levelId),
            'current' => $this->levelTag($current['id']),
            'goal' => $this->words->vocabTo($user, $current['id']),
            'left' => count($this->words->wordsToLearn($user, $levelId)),
            'batch' => (int) config('learning.rules.word_batch'),
        ];
    }

    /**
     * The road to B2: the level reached and the one being worked on (names), every level
     * with the learner's state (CourseProgressService::levelSummary, for LevelResource),
     * then the progress and the vocabulary towards B1 and towards B2.
     *
     * @return array{achieved: string|null, current: string, levels: list<array<string, mixed>>, goals: list<array{level: LevelTag, progress: float, vocab: Counts}>}
     */
    public function road(User $user): array
    {
        $achieved = $this->course->achieved($user);
        $goals = [];

        foreach (['b1', 'b2'] as $levelId) {
            if ($this->content->level($levelId) !== null) {
                $goals[] = [
                    'level' => $this->levelTag($levelId),
                    'progress' => $this->course->progressTo($user, $levelId),
                    'vocab' => $this->words->vocabTo($user, $levelId),
                ];
            }
        }

        return [
            'achieved' => $achieved === null ? null : $this->levelTag($achieved)['name'],
            'current' => $this->course->currentLevel($user)['name'] ?? 'A1',
            'levels' => array_values(array_filter(array_map(
                fn (array $level): ?array => $this->course->levelSummary($user, $level['id']),
                $this->content->levels(),
            ))),
            'goals' => $goals,
        ];
    }

    /**
     * The XP of the last seven days, oldest first; the last one is today.
     *
     * @return list<array{date: string, xp: int}>
     */
    public function week(User $user): array
    {
        $days = $this->xp->days($user);
        $week = [];

        for ($ago = 6; $ago >= 0; $ago--) {
            $date = Calendar::inDays(-$ago);
            $week[] = ['date' => $date, 'xp' => $days[$date] ?? 0];
        }

        return $week;
    }

    /**
     * The numbers of the stat cards and of the shortcuts that are not in the shared
     * learner summary.
     *
     * @return array{words: Counts, verbs: Counts, placement: bool}
     */
    public function counts(User $user): array
    {
        return [
            'words' => $this->words->counts($user),
            'verbs' => $this->verbs->counts($user),
            'placement' => $this->state->placement($user) !== null,
        ];
    }

    private function isNewcomer(User $user): bool
    {
        return $this->state->placement($user) === null
            && $this->xp->total($user) === 0
            && $this->state->exams($user)->isEmpty();
    }

    /** @return LevelTag */
    private function levelTag(string $levelId): array
    {
        $level = Level::from($levelId);

        return ['id' => $level->value, 'name' => $level->label(), 'color' => $level->color()];
    }
}
