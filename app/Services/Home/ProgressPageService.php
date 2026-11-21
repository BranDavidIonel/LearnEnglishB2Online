<?php

namespace App\Services\Home;

use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\HistoryService;
use App\Services\Progress\LearnerState;
use App\Services\Progress\VerbReviewService;
use App\Services\Progress\WordReviewService;
use App\Services\Progress\XpService;

/**
 * The progress page: what the chart draws (the XP of every day, the daily snapshots and
 * the goal) and the numbers of its stat cards.
 */
class ProgressPageService
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly LearnerState $state,
        private readonly XpService $xp,
        private readonly HistoryService $history,
        private readonly WordReviewService $words,
        private readonly VerbReviewService $verbs,
    ) {}

    /**
     * The chart's data, as the original kept it: XP per day and the daily snapshots
     * ({xp, w, v, b1, b2}), both by "Y-m-d", plus the daily goal. Today's snapshot is made
     * first when it is missing, so the line always ends on today.
     *
     * @return array{days: array<string, int>, history: array<string, array<string, int|float>>, goal: int}
     */
    public function chart(User $user): array
    {
        $this->history->touch($user);

        return [
            'days' => $this->xp->days($user),
            'history' => $this->history->history($user),
            'goal' => $this->state->profile($user)->goal,
        ];
    }

    /**
     * Units passed of all the units of the course, and the word and verb counts.
     *
     * @return array{units: array{passed: int, total: int}, words: array{learned: int, learning: int, new: int, total: int}, verbs: array{learned: int, learning: int, new: int, total: int}}
     */
    public function stats(User $user): array
    {
        return [
            'units' => [
                // Like the original, every passed unit the learner has counts, even one in review.
                'passed' => $this->state->units($user)->where('passed', true)->count(),
                'total' => array_sum(array_map(fn (array $level): int => count($level['units']), $this->content->levels())),
            ],
            'words' => $this->words->counts($user),
            'verbs' => $this->verbs->counts($user),
        ];
    }
}
