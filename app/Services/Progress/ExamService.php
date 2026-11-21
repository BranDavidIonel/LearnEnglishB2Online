<?php

namespace App\Services\Progress;

use App\Models\User;
use App\Services\Content\ContentRepository;
use Illuminate\Support\Arr;

/**
 * The level exams (original buildExam and recordExam): three questions from every unit
 * of the level, 80% to pass. A failed exam sends the units answered badly (less than 60%
 * right) back to review; a passed one clears every review of the level.
 */
class ExamService
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly LearnerState $state,
        private readonly XpService $xp,
    ) {}

    /**
     * Three random exercises of every unit of the level ("match" exercises left out), unit
     * after unit; the page shuffles them. Each item: {ex, unit, key: "<unit>:<index>"}.
     *
     * @return list<array{ex: array<string, mixed>, unit: string, key: string}>
     */
    public function buildExam(string $levelId): array
    {
        $items = [];

        foreach ($this->content->units($levelId) as $unit) {
            $pool = [];

            foreach ($unit['exercises'] as $index => $exercise) {
                if (($exercise['type'] ?? null) !== 'match') {
                    $pool[] = ['ex' => $exercise, 'unit' => (string) $unit['id'], 'key' => "{$unit['id']}:{$index}"];
                }
            }

            $take = min((int) config('learning.rules.exam_items_per_unit'), count($pool));
            $items = [...$items, ...array_values(Arr::random($pool, $take))];
        }

        return $items;
    }

    /**
     * Records an exam from its answers ([{unit, correct}]). Passing gives 150 XP the first
     * time and 20 XP after that.
     *
     * @param  list<array{unit: string, correct: bool}>  $results
     * @return array{score: float, passed: bool, first: bool, xp: int, weak: list<string>, byUnit: array<string, array{ok: int, n: int}>}
     */
    public function recordExam(User $user, string $levelId, array $results): array
    {
        $score = count(array_filter($results, fn (array $result): bool => $result['correct'])) / count($results);
        $byUnit = $this->byUnit($results);

        $exam = $this->state->exam($user, $levelId);
        $exam->tries++;
        $exam->best = max($exam->best, $score);
        $exam->last_taken_on = Calendar::today();
        $passed = $score >= (float) config('learning.rules.pass');
        $first = $passed && ! $exam->passed;
        $weak = $this->weakUnits($user, $levelId, $byUnit, $passed);
        $xp = 0;

        if ($passed) {
            $exam->passed = true;
            $xp = (int) config($first ? 'learning.xp.exam_first' : 'learning.xp.exam_again');
            $this->clearReview($user, $levelId);
        }

        $exam->save();
        $this->xp->award($user, $xp);

        return ['score' => $score, 'passed' => $passed, 'first' => $first, 'xp' => $xp, 'weak' => $weak, 'byUnit' => $byUnit];
    }

    /**
     * @param  list<array{unit: string, correct: bool}>  $results
     * @return array<string, array{ok: int, n: int}>
     */
    private function byUnit(array $results): array
    {
        $byUnit = [];

        foreach ($results as $result) {
            $byUnit[$result['unit']] ??= ['ok' => 0, 'n' => 0];
            $byUnit[$result['unit']]['n']++;
            $byUnit[$result['unit']]['ok'] += $result['correct'] ? 1 : 0;
        }

        return $byUnit;
    }

    /**
     * The units of the level with less than 60% right; after a failed exam they go back to review.
     *
     * @param  array<string, array{ok: int, n: int}>  $byUnit
     * @return list<string>
     */
    private function weakUnits(User $user, string $levelId, array $byUnit, bool $passed): array
    {
        $weak = [];

        foreach ($this->content->level($levelId)['units'] ?? [] as $unit) {
            $answers = $byUnit[$unit['id']] ?? null;

            if ($answers === null || (float) config('learning.rules.weak_unit') <= $answers['ok'] / $answers['n']) {
                continue;
            }

            $weak[] = $unit['id'];

            if (! $passed) {
                $state = $this->state->unit($user, $unit['id']);
                $state->review = true;
                $state->save();
            }
        }

        return $weak;
    }

    /** A passed exam: no unit of the level is in review any more (every unit gets a state, like the original). */
    private function clearReview(User $user, string $levelId): void
    {
        foreach ($this->content->level($levelId)['units'] ?? [] as $unit) {
            $state = $this->state->unit($user, $unit['id']);
            $state->review = false;
            $state->save();
        }
    }
}
