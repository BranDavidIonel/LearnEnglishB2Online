<?php

namespace App\Services\Progress;

use App\Models\ProgressSnapshot;
use App\Models\User;
use App\Services\Content\ContentRepository;

/**
 * One snapshot per day of the numbers drawn on the progress chart (original snapshot):
 * total XP, words learned, irregular verbs learned, and the % of the road to B1 and to B2.
 * Today's snapshot is refreshed after every change (the progress API does it with every
 * answer) and made on the first page of a day the learner opens.
 */
class HistoryService
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly LearnerState $state,
        private readonly CourseProgressService $course,
        private readonly WordReviewService $words,
        private readonly VerbReviewService $verbs,
    ) {}

    /** Writes (or rewrites) today's snapshot. */
    public function snapshot(User $user): ProgressSnapshot
    {
        $snapshot = $user->snapshots()->firstOrNew(['day' => Calendar::todayString()]);
        $snapshot->fill([
            'xp' => $this->state->profile($user)->xp,
            'words' => $this->words->counts($user)['learned'],
            'verbs' => $this->verbs->counts($user)['learned'],
            'b1' => $this->percentTo($user, 'b1'),
            'b2' => $this->percentTo($user, 'b2'),
        ]);
        $snapshot->save();

        return $snapshot;
    }

    /**
     * Makes today's snapshot when the learner has progress and there is none yet (the
     * original refreshed it whenever the app was opened, so the chart has a point for
     * every day the app was used).
     */
    public function touch(User $user): void
    {
        if (! $this->state->profile($user)->exists) {
            return;
        }

        if (! $user->snapshots()->where('day', Calendar::todayString())->exists()) {
            $this->snapshot($user);
        }
    }

    /**
     * Every snapshot, oldest first, as the original stored them (keys missing in old ones stay out).
     *
     * @return array<string, array{xp: int, w?: int, v?: int, b1?: float, b2?: float}>
     */
    public function history(User $user): array
    {
        return $user->snapshots()->orderBy('day')->get()
            ->mapWithKeys(fn (ProgressSnapshot $snapshot): array => [$snapshot->day->toDateString() => array_filter([
                'xp' => $snapshot->xp,
                'w' => $snapshot->words,
                'v' => $snapshot->verbs,
                'b1' => $snapshot->b1,
                'b2' => $snapshot->b2,
            ], fn (int|float|null $value): bool => $value !== null)])
            ->all();
    }

    /** % of the road to a level, one decimal (0 when the course has no such level). */
    private function percentTo(User $user, string $levelId): float
    {
        if ($this->content->level($levelId) === null) {
            return 0.0;
        }

        return round($this->course->progressTo($user, $levelId) * 1000) / 10;
    }
}
