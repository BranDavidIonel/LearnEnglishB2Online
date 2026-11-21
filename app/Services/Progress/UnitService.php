<?php

namespace App\Services\Progress;

use App\Enums\UnitStep;
use App\Models\UnitProgress;
use App\Models\User;

/**
 * The learner's work in a unit: lesson steps done and unit tests taken
 * (original completeStep and recordUnitTest).
 */
class UnitService
{
    public function __construct(
        private readonly LearnerState $state,
        private readonly XpService $xp,
        private readonly WordReviewService $words,
    ) {}

    /**
     * Marks a lesson step done. The vocabulary step puts the unit's core words into the
     * flashcard deck. The first time a step is done gives 10 XP (the practice step gives
     * none: its XP depends on the answers, the page adds it).
     *
     * @return array{first: bool, xp: int, unit: UnitProgress}
     */
    public function completeStep(User $user, string $unitId, UnitStep $step): array
    {
        $unit = $this->state->unit($user, $unitId);
        $first = ! $unit->hasStep($step);
        $unit->addStep($step);
        $unit->save();

        if ($step === UnitStep::Vocab) {
            $this->words->addUnitWords($user, $unitId);
        }

        $xp = $first ? $this->stepXp($step) : 0;
        $this->xp->award($user, $xp);

        return ['first' => $first, 'xp' => $xp, 'unit' => $unit];
    }

    /**
     * Records a unit test (score 0..1). From 80% the unit is passed (and leaves review):
     * 30 XP the first time it is passed, 10 XP after that; below, no XP.
     *
     * @return array{passed: bool, first: bool, xp: int, unit: UnitProgress}
     */
    public function recordUnitTest(User $user, string $unitId, float $score): array
    {
        $unit = $this->state->unit($user, $unitId);
        $unit->tries++;
        $unit->best = max($unit->best, $score);
        $passed = $score >= (float) config('learning.rules.pass');
        $first = $passed && ! $unit->passed;
        $xp = 0;

        if ($passed) {
            $unit->passed = true;
            $unit->review = false;
            $xp = (int) config($first ? 'learning.xp.unit_test_first' : 'learning.xp.unit_test_again');
        }

        $unit->save();
        $this->xp->award($user, $xp);

        return ['passed' => $passed, 'first' => $first, 'xp' => $xp, 'unit' => $unit];
    }

    private function stepXp(UnitStep $step): int
    {
        return (int) config($step === UnitStep::Practice ? 'learning.xp.practice_step' : 'learning.xp.step');
    }
}
