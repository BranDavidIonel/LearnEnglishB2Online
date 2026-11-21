<?php

namespace App\Services\Progress;

use App\Models\Placement;
use App\Models\User;
use App\Services\Content\ContentRepository;

/**
 * The placement test (original recordPlacement): the learner starts at the first level
 * scored below 75%, counting from A1 without a gap (never past the last level). The
 * levels before it count as known. A new test replaces the old result; it gives 20 XP.
 */
class PlacementService
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly LearnerState $state,
        private readonly XpService $xp,
    ) {}

    /**
     * @param  array<string, float|int>  $scores  level id => score 0..1, for the levels tested
     * @return array{start: int, level: string, xp: int, placement: Placement}
     */
    public function recordPlacement(User $user, array $scores): array
    {
        $levels = $this->content->levels();
        $start = 0;

        foreach ($levels as $level) {
            if (($scores[$level['id']] ?? 0) < (float) config('learning.rules.placement_pass')) {
                break;
            }

            $start = $level['index'] + 1;
        }

        $start = min($start, count($levels) - 1);
        $placement = $this->state->placement($user) ?? new Placement;
        $placement->user_id = $user->id;
        $placement->fill(['start_index' => $start, 'scores' => $scores, 'taken_on' => Calendar::today()]);
        $placement->save();
        $this->state->forget($user, 'placement');

        $xp = (int) config('learning.xp.placement');
        $this->xp->award($user, $xp);

        return ['start' => $start, 'level' => $levels[$start]['id'], 'xp' => $xp, 'placement' => $placement];
    }

    /**
     * The last placement result as the original stored it, or null without a test.
     *
     * @return array{start: int, level: string|null, scores: array<string, float|int>, date: string|null}|null
     */
    public function placement(User $user): ?array
    {
        $placement = $this->state->placement($user);

        if ($placement === null) {
            return null;
        }

        return [
            'start' => $placement->start_index,
            'level' => $this->content->levels()[$placement->start_index]['id'] ?? null,
            'scores' => $placement->scores,
            'date' => $placement->taken_on?->toDateString(),
        ];
    }
}
