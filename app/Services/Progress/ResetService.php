<?php

namespace App\Services\Progress;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes all of a learner's progress (original resetAll): XP, streak, course, words,
 * verbs, mistakes, records, Cambridge results, history, and the settings, which go back
 * to the defaults. The account and its interface language stay.
 */
class ResetService
{
    public function __construct(private readonly LearnerState $state) {}

    public function reset(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->cambridgeResults()->delete();
            $user->cambridgeTests()->delete();
            $user->speakingScores()->delete();
            $user->bestScores()->delete();
            $user->mistakes()->delete();
            $user->verbReviews()->delete();
            $user->wordReviews()->delete();
            $user->examResults()->delete();
            $user->unitProgress()->delete();
            $user->snapshots()->delete();
            $user->dailyXp()->delete();
            $user->placement()->delete();
            $user->profile()->delete();
        });

        $this->state->forget($user);
    }
}
