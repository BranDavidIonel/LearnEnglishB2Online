<?php

namespace App\Services\Progress;

use App\Enums\NextActionKind;
use App\Enums\UnitStep;
use App\Models\User;
use App\Services\Content\ContentRepository;

/**
 * Where the learner is in the course (read only): which levels and units are open,
 * done or skipped, how far each one is, the level being worked on and what to do next.
 * A port of the "units / levels" part of the original store.
 *
 * - The placement test opens every level up to the one it found, and the levels before
 *   that one count as known ("skipped") until their exam is passed.
 * - A level opens when the one before it is done (exam passed, or skipped).
 * - In an open level the first unit is open; the next one opens when the one before it is
 *   passed; every unit is open once the level is done.
 * - Unit progress: the five lesson steps are worth 80%, a passed test 100% (unless a failed
 *   level exam sent the unit back to review). Level progress: 90% from its units, 100% done.
 *
 * @phpstan-import-type LevelOutline from ContentRepository
 */
class CourseProgressService
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly LearnerState $state,
    ) {}

    /** Index of the level the placement test put the learner in (0 without a test). */
    public function startIndex(User $user): int
    {
        return $this->state->placement($user)->start_index ?? 0;
    }

    public function levelPassed(User $user, string $levelId): bool
    {
        return (bool) $this->state->exams($user)->get($levelId)?->passed;
    }

    /** Below the placement level and its exam not passed: counts as known. */
    public function levelSkipped(User $user, string $levelId): bool
    {
        $level = $this->content->level($levelId);

        return $level !== null && $level['index'] < $this->startIndex($user) && ! $this->levelPassed($user, $levelId);
    }

    public function levelDone(User $user, string $levelId): bool
    {
        return $this->levelPassed($user, $levelId) || $this->levelSkipped($user, $levelId);
    }

    public function levelUnlocked(User $user, string $levelId): bool
    {
        $level = $this->content->level($levelId);

        if ($level === null) {
            return false;
        }

        if ($level['index'] === 0 || $level['index'] <= $this->startIndex($user)) {
            return true;
        }

        $previous = $this->content->levels()[$level['index'] - 1] ?? null;

        return $previous !== null && $this->levelDone($user, $previous['id']);
    }

    public function unitUnlocked(User $user, string $unitId): bool
    {
        $unit = $this->content->unitOutline($unitId);

        if ($unit === null || ! $this->levelUnlocked($user, $unit['levelId'])) {
            return false;
        }

        if ($unit['index'] === 0 || $this->levelDone($user, $unit['levelId'])) {
            return true;
        }

        $state = $this->state->peekUnit($user, $unitId);

        if ($state !== null && ($state->passed || $state->review)) {
            return true;
        }

        $previous = $this->content->level($unit['levelId'])['units'][$unit['index'] - 1]['id'] ?? null;

        return (bool) $this->state->peekUnit($user, (string) $previous)?->passed;
    }

    /**
     * The learner's state in a unit, as the original stored it (defaults when untouched).
     *
     * @return array{steps: list<string>, best: float, passed: bool, review: bool, tries: int}
     */
    public function unitState(User $user, string $unitId): array
    {
        $state = $this->state->peekUnit($user, $unitId);

        return [
            'steps' => $state === null ? [] : array_values(array_intersect(UnitStep::lessonValues(), $state->steps)),
            'best' => $state->best ?? 0.0,
            'passed' => $state->passed ?? false,
            'review' => $state->review ?? false,
            'tries' => $state->tries ?? 0,
        ];
    }

    /** 0..1: the lesson steps are worth 80%; a passed unit (not in review) is complete. */
    public function unitProgress(User $user, string $unitId): float
    {
        $state = $this->unitState($user, $unitId);

        if ($state['passed'] && ! $state['review']) {
            return 1.0;
        }

        return count($state['steps']) / count(UnitStep::lessons()) * (float) config('learning.rules.unit_steps_share');
    }

    /** 0..1: a done level is complete; otherwise 90% of the average of its units. */
    public function levelProgress(User $user, string $levelId): float
    {
        if ($this->levelDone($user, $levelId)) {
            return 1.0;
        }

        $units = $this->content->level($levelId)['units'] ?? [];

        if ($units === []) {
            return 0.0;
        }

        $sum = array_sum(array_map(fn (array $unit): float => $this->unitProgress($user, $unit['id']), $units));

        return (float) config('learning.rules.level_units_share') * $sum / count($units);
    }

    /** 0..1: how far the learner is on the road to finishing a level, counting every level before it. */
    public function progressTo(User $user, string $levelId): float
    {
        $level = $this->content->level($levelId);

        if ($level === null) {
            return 0.0;
        }

        $levels = array_slice($this->content->levels(), 0, $level['index'] + 1);
        $sum = array_sum(array_map(fn (array $item): float => $this->levelProgress($user, $item['id']), $levels));

        return $sum / count($levels);
    }

    /**
     * The level being worked on: the first one not done that has units, else the last one.
     *
     * @return LevelOutline|null
     */
    public function currentLevel(User $user): ?array
    {
        $levels = $this->content->levels();

        foreach ($levels as $level) {
            if ($level['units'] !== [] && ! $this->levelDone($user, $level['id'])) {
                return $level;
            }
        }

        return $levels === [] ? null : $levels[array_key_last($levels)];
    }

    /** The highest level finished, counting from A1 without a gap (null when A1 is not done). */
    public function achieved(User $user): ?string
    {
        $best = null;

        foreach ($this->content->levels() as $level) {
            if (! $this->levelDone($user, $level['id'])) {
                break;
            }

            $best = $level['id'];
        }

        return $best;
    }

    /**
     * The units of a level that a failed exam sent back to review, in course order.
     *
     * @return list<string> unit ids
     */
    public function reviewUnits(User $user, string $levelId): array
    {
        $ids = [];

        foreach ($this->content->level($levelId)['units'] ?? [] as $unit) {
            if ($this->state->peekUnit($user, $unit['id'])?->review) {
                $ids[] = $unit['id'];
            }
        }

        return $ids;
    }

    /**
     * What to do next: the test of the first unit to review, else the first lesson step
     * not done of the first unit not passed (or its test), else the level exam.
     *
     * @return array{kind: NextActionKind, level: string|null, unit: string|null, step: string|null, review: bool}
     */
    public function nextAction(User $user): array
    {
        $level = $this->currentLevel($user);
        $action = ['kind' => NextActionKind::Done, 'level' => null, 'unit' => null, 'step' => null, 'review' => false];

        if ($level === null || $level['units'] === []) {
            return $action;
        }

        $action['level'] = $level['id'];

        if ($this->levelDone($user, $level['id'])) {
            return $action;
        }

        $review = $this->reviewUnits($user, $level['id']);

        if ($review !== []) {
            return [...$action, 'kind' => NextActionKind::Unit, 'unit' => $review[0], 'step' => UnitStep::Test->value, 'review' => true];
        }

        foreach ($level['units'] as $unit) {
            $state = $this->unitState($user, $unit['id']);

            if ($state['passed']) {
                continue;
            }

            $step = array_values(array_diff(UnitStep::lessonValues(), $state['steps']))[0] ?? UnitStep::Test->value;

            return [...$action, 'kind' => NextActionKind::Unit, 'unit' => $unit['id'], 'step' => $step];
        }

        return [...$action, 'kind' => NextActionKind::Exam];
    }

    /**
     * A unit for the pages (see UnitResource): its outline, the learner's state, whether it
     * is open and its progress. Null when there is no such unit.
     *
     * @return array<string, mixed>|null
     */
    public function unitSummary(User $user, string $unitId): ?array
    {
        $unit = $this->content->unitOutline($unitId);

        if ($unit === null) {
            return null;
        }

        return [
            ...$unit,
            ...$this->unitState($user, $unitId),
            'unlocked' => $this->unitUnlocked($user, $unitId),
            'progress' => $this->unitProgress($user, $unitId),
        ];
    }

    /**
     * A level for the pages (see LevelResource): open, done, passed, skipped, progress
     * and the exam state. Null when there is no such level.
     *
     * @return array<string, mixed>|null
     */
    public function levelSummary(User $user, string $levelId): ?array
    {
        $level = $this->content->level($levelId);

        if ($level === null) {
            return null;
        }

        return [
            'id' => $level['id'],
            'name' => $level['name'],
            'index' => $level['index'],
            'units' => count($level['units']),
            'unlocked' => $this->levelUnlocked($user, $levelId),
            'done' => $this->levelDone($user, $levelId),
            'passed' => $this->levelPassed($user, $levelId),
            'skipped' => $this->levelSkipped($user, $levelId),
            'progress' => $this->levelProgress($user, $levelId),
            'exam' => $this->examState($user, $levelId),
        ];
    }

    /**
     * The learner's exam of a level as the original stored it, or null when never taken.
     *
     * @return array{best: float, passed: bool, tries: int, last: string|null}|null
     */
    public function examState(User $user, string $levelId): ?array
    {
        $exam = $this->state->exams($user)->get($levelId);

        if ($exam === null || ! $exam->exists) {
            return null;
        }

        return ['best' => $exam->best, 'passed' => $exam->passed, 'tries' => $exam->tries, 'last' => $exam->last_taken_on?->toDateString()];
    }
}
