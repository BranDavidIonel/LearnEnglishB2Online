<?php

namespace App\Services\Progress;

use App\Models\User;
use App\Services\Content\WordFinder;
use App\Services\Content\WordMatch;

/**
 * The word window: what a word tapped in a lesson is (WordFinder), the expressions of
 * its sentence that hold it, and where its headword stands for the learner when it can be
 * learned (a course word or a glossary word; not a name, not an irregular verb on its own,
 * which has its own drill). A read: nothing is written.
 */
class WordLookupService
{
    public function __construct(
        private readonly WordFinder $finder,
        private readonly WordReviewService $words,
    ) {}

    /**
     * @return array{word: string, match: WordMatch|null, state: array{status: string, box: int, due: string|null, known: bool}|null, phrases: list<array{en: string, ro: string}>}
     */
    public function lookup(User $user, string $form, ?string $sentence = null): array
    {
        $word = WordFinder::words($form)[0] ?? trim($form);
        $match = $this->finder->find($word, $sentence !== null && WordFinder::startsSentence($sentence, $word));

        return [
            'word' => $word,
            'match' => $match,
            'state' => $match !== null && $match->learnable ? $this->words->state($user, $match->headword) : null,
            'phrases' => $sentence === null ? [] : $this->finder->expressions($sentence, $word),
        ];
    }
}
