<?php

namespace App\Services\Games;

use App\Enums\Game;
use App\Models\User;
use App\Services\Progress\WordReviewService;

/**
 * The words a game is played with: the words the learner has met (WordReviewService::knownWords()),
 * only what the game shows (English, Romanian, emoji). The page picks and shuffles them per round.
 */
class GameWords
{
    // Dictation plays rounds of 10 words, so it asks for at least 10 (the other games for the default 8).
    private const int SPELL_MIN = 10;

    // Dictation leaves out long phrases: a word with a space is kept only when it is short.
    private const int SPELL_PHRASE_MAX = 18;

    public function __construct(private readonly WordReviewService $words) {}

    /** @return list<array{en: string, ro: string, emoji: string}> */
    public function for(User $user, Game $game): array
    {
        $words = $game === Game::Spell
            ? array_filter($this->words->knownWords($user, self::SPELL_MIN), $this->spellable(...))
            : $this->words->knownWords($user);

        return array_values(array_map(fn (array $word): array => [
            'en' => (string) $word['en'],
            'ro' => (string) $word['ro'],
            'emoji' => (string) ($word['emoji'] ?? ''),
        ], $words));
    }

    /** @param  array<string, mixed>  $word */
    private function spellable(array $word): bool
    {
        $en = (string) $word['en'];

        return ! preg_match('/\s/', $en) || mb_strlen($en) < self::SPELL_PHRASE_MAX;
    }
}
