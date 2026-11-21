<?php

namespace App\Services\Words;

use App\Enums\WordStatus;
use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\LearnerState;
use App\Services\Progress\SettingsService;
use App\Services\Progress\WordReviewService;
use Illuminate\Support\Arr;

/**
 * What the word pages show: "My words", the word bank of a unit, the quick sort of a level
 * and the flashcards. The rules (status, due words, words to learn) stay in
 * WordReviewService; this class only picks and shapes what each page needs.
 */
class WordPages
{
    /** Cards of one review session ("Start review" takes the first 30 due). */
    public const int REVIEW_SESSION = 30;

    /** Cards of a free practice session. */
    public const int PRACTICE_SESSION = 20;

    public function __construct(
        private readonly ContentRepository $content,
        private readonly WordReviewService $words,
        private readonly SettingsService $settings,
        private readonly LearnerState $state,
        private readonly MeaningOptions $meanings,
    ) {}

    /**
     * Every word of the course with the learner's state, in course order (the rows of "My words").
     *
     * @return list<array<string, mixed>>
     */
    public function all(User $user): array
    {
        return $this->withState($user, array_values($this->content->words()));
    }

    /**
     * What the rows of "My words" leave out, by English text: part of speech, example,
     * picture and the unit that teaches the word. Sent after the list, for the word details.
     *
     * @return array<string, array<string, mixed>>
     */
    public function details(): array
    {
        $details = [];

        foreach ($this->content->words() as $en => $word) {
            $details[(string) $en] = [
                'pos' => $word['pos'],
                'ex' => $word['ex'],
                'exRo' => $word['exRo'],
                ...(isset($word['wiki']) ? ['wiki' => $word['wiki']] : []),
                'unit' => $word['unit'],
            ];
        }

        return $details;
    }

    /**
     * The "A1 · Unit 3 · Title" of the units (all of them, or the ones given), by unit id.
     *
     * @param  list<string>|null  $ids
     * @return array<string, array{level: string, order: int|null, title: string|null}>
     */
    public function unitLabels(?array $ids = null): array
    {
        $labels = [];

        foreach ($this->content->levels() as $level) {
            foreach ($level['units'] as $unit) {
                if ($ids === null || in_array($unit['id'], $ids, true)) {
                    $labels[$unit['id']] = ['level' => $level['name'], 'order' => $unit['order'], 'title' => $unit['title']];
                }
            }
        }

        return $labels;
    }

    /**
     * The words of the levels the placement test skipped that are still "to learn", for the
     * banner of "My words": their English texts and the level of the first one (where the
     * quick check starts). Null when there are none or the learner chose to go through them.
     *
     * @return array{words: list<string>, level: string}|null
     */
    public function skipped(User $user): ?array
    {
        if ($this->settings->flags($user)['keepSkippedWords'] ?? false) {
            return null;
        }

        $words = $this->words->skippedLevelWords($user);

        if ($words === []) {
            return null;
        }

        return [
            'words' => array_map(fn (array $word): string => (string) $word['en'], $words),
            'level' => (string) $words[0]['levelId'],
        ];
    }

    /**
     * The word bank of a unit with the learner's state.
     *
     * @return list<array<string, mixed>>
     */
    public function bank(User $user, string $unitId): array
    {
        return $this->withState($user, $this->words->bankWords($unitId));
    }

    /**
     * The words of a unit's bank still "to learn", in their order.
     *
     * @return list<array<string, mixed>>
     */
    public function bankToLearn(User $user, string $unitId): array
    {
        return array_values(array_filter(
            $this->words->bankWords($unitId),
            fn (array $word): bool => $this->words->status($user, (string) $word['en']) === WordStatus::New,
        ));
    }

    /**
     * Three wrong meanings for the mini lesson of each word, by English text (on pages that
     * do not have the whole word list at hand).
     *
     * @param  list<array<string, mixed>>  $words
     * @return array<string, list<string>>
     */
    public function lessonChoices(array $words): array
    {
        $byLevel = [];

        foreach ($this->content->words() as $word) {
            $byLevel[$word['levelId']][] = $word;
        }

        $choices = [];

        foreach ($words as $word) {
            $choices[(string) $word['en']] = $this->meanings->lesson($word, $byLevel[$word['levelId']] ?? []);
        }

        return $choices;
    }

    /**
     * The next quick-sort batch ("learn the next 20"): how many words are left and the first
     * batch, each word with the learner's state and the four meanings of its quiz question
     * (taken from the words of its level, as the original did).
     *
     * @param  list<array<string, mixed>>  $todo  the words still to learn, in course order
     * @return array{count: int, batch: list<array<string, mixed>>}
     */
    public function batch(User $user, array $todo): array
    {
        $pools = [];
        $batch = [];

        foreach (array_slice($todo, 0, $this->batchSize()) as $word) {
            $level = (string) $word['levelId'];
            $pools[$level] ??= $this->words->levelWords($level);
            $batch[] = [...$this->words->withState($user, $word), 'options' => $this->meanings->quiz($word, $pools[$level])];
        }

        return ['count' => count($todo), 'batch' => $batch];
    }

    /** How many words a quick-sort batch hands out. */
    public function batchSize(): int
    {
        return (int) config('learning.rules.word_batch');
    }

    /**
     * The cards of the next review session: the most overdue words first.
     *
     * @return list<array<string, mixed>>
     */
    public function due(User $user): array
    {
        return $this->withState($user, array_slice($this->words->due($user), 0, self::REVIEW_SESSION));
    }

    /**
     * Random words for free practice: the words met so far (or the first words of the
     * current level when there are too few).
     *
     * @return list<array<string, mixed>>
     */
    public function practice(User $user): array
    {
        $words = array_values(Arr::shuffle($this->words->knownWords($user)));

        return $this->withState($user, array_slice($words, 0, self::PRACTICE_SESSION));
    }

    /** How many words are in the flashcard deck (also the ones no longer in the course). */
    public function inDeck(User $user): int
    {
        return $this->state->words($user)->count();
    }

    /**
     * @param  list<array<string, mixed>>  $words
     * @return list<array<string, mixed>>
     */
    private function withState(User $user, array $words): array
    {
        return array_map(fn (array $word): array => $this->words->withState($user, $word), $words);
    }
}
