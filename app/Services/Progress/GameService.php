<?php

namespace App\Services\Progress;

use App\Enums\Game;
use App\Enums\ScoreArea;
use App\Models\User;

/**
 * The records of the word games (what the original games page kept in "games"):
 * the fewest moves at Pairs, the most points at Sprint 60, the best Dictation score.
 * The XP of a game is added by the page (it depends on the round), see XpService.
 */
class GameService
{
    /**
     * The records: game => value.
     *
     * @return array<string, int>
     */
    public function records(User $user): array
    {
        return $user->bestScores()->where('area', ScoreArea::Games)->orderBy('id')->pluck('value', 'game')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();
    }

    /** Keeps a result when it beats the record; returns true for a new record. */
    public function record(User $user, Game $game, int $value): bool
    {
        $row = $user->bestScores()->firstOrNew(['area' => ScoreArea::Games, 'game' => $game->value]);

        return $row->keepIfBetter($value, $game->lowerIsBetter());
    }
}
