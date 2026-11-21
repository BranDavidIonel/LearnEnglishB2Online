<?php

namespace App\Services\Progress;

use App\Enums\ScoreArea;
use App\Enums\SpeakingGame;
use App\Models\User;

/**
 * The speaking practice (original speakState, countSpoken, recordConversation,
 * speakRecord): the sentences said well enough, the best score of every conversation and
 * role-play, and the records of the speaking games.
 */
class SpeakingService
{
    public function __construct(
        private readonly LearnerState $state,
        private readonly XpService $xp,
    ) {}

    /**
     * As the original kept it: {said, scen: {id: best score}, best: {game: record}}.
     *
     * @return array{said: int, scen: array<string, float>, best: array<string, int>}
     */
    public function state(User $user): array
    {
        return [
            'said' => $this->state->profile($user)->spoken,
            'scen' => $user->speakingScores()->orderBy('id')->pluck('score', 'scenario')->map(fn (mixed $score): float => (float) $score)->all(),
            'best' => $user->bestScores()->where('area', ScoreArea::Speaking)->orderBy('id')->pluck('value', 'game')->map(fn (mixed $value): int => (int) $value)->all(),
        ];
    }

    /**
     * Counts sentences said well enough: 2 XP each (3 for an answer to an interview question).
     *
     * @return int the XP given
     */
    public function countSpoken(User $user, int $count, int $xpEach): int
    {
        $profile = $this->state->profile($user);
        $profile->spoken += $count;
        $profile->save();

        $xp = $count * $xpEach;
        $this->xp->award($user, $xp);

        return $xp;
    }

    /**
     * Stores the result (0..1) of a conversation or role-play, keeping the best score:
     * 15 XP the first time, 5 XP after that.
     *
     * @return array{first: bool, xp: int, best: float}
     */
    public function recordConversation(User $user, string $scenario, float $score): array
    {
        $row = $user->speakingScores()->firstOrNew(['scenario' => $scenario]);
        $first = ! $row->exists;
        $row->score = max($first ? 0.0 : $row->score, $score);
        $row->save();

        $xp = (int) config($first ? 'learning.xp.conversation_first' : 'learning.xp.conversation_again');
        $this->xp->award($user, $xp);

        return ['first' => $first, 'xp' => $xp, 'best' => $row->score];
    }

    /**
     * Keeps the best result of a speaking game (the higher, the better).
     *
     * @return bool true for a new record
     */
    public function record(User $user, SpeakingGame $game, int $value): bool
    {
        $row = $user->bestScores()->firstOrNew(['area' => ScoreArea::Speaking, 'game' => $game->value]);

        return $row->keepIfBetter($value, lowerIsBetter: false);
    }
}
