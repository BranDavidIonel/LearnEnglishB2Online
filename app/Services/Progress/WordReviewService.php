<?php

namespace App\Services\Progress;

use App\Enums\WordStatus;
use App\Models\User;
use App\Models\WordReview;
use App\Services\Content\ContentRepository;

/**
 * The words and their spaced repetition (the "vocabulary" part of the original store).
 *
 * A word without a review row is "new" (to learn); in the deck it is "learning" until its
 * box reaches 3, then "learned". Grading a flashcard moves the box: 0 = did not know
 * (back to box 0, again today), 1 = hard (at least box 1, tomorrow), 2 = knew it (one box
 * up, next review after the interval of the new box: 0, 1, 3, 7, 14, 30, 60 days).
 * Words are arrays of the word index (ContentRepository::words()).
 *
 * Glossary words can be learned too ("extra words", ContentRepository::extraWords()): same
 * table, same boxes, same answers, due on the flashcards like the others — but every count
 * of the course (word totals, level and unit counts, vocabTo, words to learn, the badges,
 * the chart) stays computed from the course vocabulary only.
 */
class WordReviewService
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly LearnerState $state,
        private readonly CourseProgressService $course,
    ) {}

    public function status(User $user, string $en): WordStatus
    {
        $review = $this->state->words($user)->get($en);

        if ($review === null) {
            return WordStatus::New;
        }

        return $review->box >= $this->learnedBox() ? WordStatus::Learned : WordStatus::Learning;
    }

    /**
     * How many words of a list are learned, learning and new (default: every word of the course).
     *
     * @param  iterable<array<string, mixed>|string>|null  $words  word arrays or English texts
     * @return array{learned: int, learning: int, new: int, total: int}
     */
    public function counts(User $user, ?iterable $words = null): array
    {
        $words ??= array_keys($this->content->wordLevels());
        $counts = ['learned' => 0, 'learning' => 0, 'new' => 0, 'total' => 0];

        foreach ($words as $word) {
            $counts[$this->status($user, is_array($word) ? (string) $word['en'] : (string) $word)->value]++;
            $counts['total']++;
        }

        return $counts;
    }

    /**
     * The words to review today (due today or earlier, still in the course or the glossary),
     * the most overdue first. Extra words come as cards (see card()).
     *
     * @return list<array<string, mixed>>
     */
    public function due(User $user): array
    {
        return array_map(
            fn (WordReview $review): array => (array) $this->card($review->word),
            $this->dueReviews($user, $this->learnable()),
        );
    }

    /** How many words are due today (the flashcards badge), extra words included. */
    public function dueCount(User $user): int
    {
        return count($this->dueReviews($user, $this->learnable()));
    }

    /** Whether a word can go into the deck: a word of the course or an extra word of the glossary. */
    public function canLearn(string $en): bool
    {
        return isset($this->content->wordLevels()[$en]) || $this->content->extraWord($en) !== null;
    }

    /**
     * A word as a flashcard shows it: the word of the course, or an extra word with what the
     * glossary has (en, ro, ipa, pos), no example, no unit, no level, and extra: true.
     * Null for a word that is neither.
     *
     * @return array<string, mixed>|null
     */
    public function card(string $en): ?array
    {
        $word = $this->content->word($en);

        if ($word !== null) {
            return $word;
        }

        $extra = $this->content->extraWord($en);

        return $extra === null ? null : [
            'en' => (string) $extra['en'],
            'ro' => (string) ($extra['ro'] ?? ''),
            'ipa' => $extra['ipa'] ?? null,
            'pos' => $extra['pos'] ?? null,
            'emoji' => null,
            'ex' => null,
            'exRo' => null,
            'unit' => null,
            'levelId' => null,
            'extra' => true,
        ];
    }

    /**
     * The extra words in the learner's deck, as cards with their state, in the order they
     * joined it (for the "Extra words" list of the words page).
     *
     * @return list<array<string, mixed>>
     */
    public function extraWords(User $user): array
    {
        $extra = $this->content->extraWords();
        $words = [];

        foreach ($this->state->words($user) as $review) {
            if (isset($extra[$review->word]) && ! isset($this->content->wordLevels()[$review->word])) {
                $words[] = $this->withState($user, (array) $this->card($review->word));
            }
        }

        return $words;
    }

    /**
     * Where a word stands for the learner (the word window): status, box (0 when not in the
     * deck), next review (Y-m-d) and "known".
     *
     * @return array{status: string, box: int, due: string|null, known: bool}
     */
    public function state(User $user, string $en): array
    {
        $review = $this->state->words($user)->get($en);

        return [
            'status' => $this->status($user, $en)->value,
            'box' => $review->box ?? 0,
            'due' => $review?->due_on->toDateString(),
            'known' => (bool) $review?->known,
        ];
    }

    /** Grades a flashcard: 0 = did not know, 1 = hard, 2 = knew it. A word not in the deck joins it. */
    public function grade(User $user, string $en, int $grade): WordReview
    {
        $review = $this->state->words($user)->get($en) ?? $this->entry($user, $en, 0, 0);
        $intervals = $this->intervals();

        if ($grade === 0) {
            $review->box = 0;
            $review->bad++;
            $review->due_on = Calendar::today();
        } elseif ($grade === 1) {
            $review->box = max(1, $review->box);
            $review->due_on = Calendar::today()->addDay();
            $review->ok++;
        } else {
            $review->box = min($review->box + 1, count($intervals) - 1);
            $review->due_on = Calendar::today()->addDays($intervals[$review->box]);
            $review->ok++;
        }

        $review->save();

        return $review;
    }

    /** "I already know this word": counts as learned and comes back for a check only after two months. */
    public function markKnown(User $user, string $en): WordReview
    {
        $last = count($this->intervals()) - 1;

        return $this->replace($user, $en, $last, $this->intervals()[$last], ok: 0, known: true);
    }

    /** Puts a word into the flashcard deck as something to learn (or to learn again), due today. */
    public function learn(User $user, string $en): WordReview
    {
        return $this->replace($user, $en, 0, 0, ok: 0, known: false);
    }

    /** "I knew it" in a quick sort: counts as learned and comes back on the flashcards in a week, as a check. */
    public function sortedKnown(User $user, string $en): WordReview
    {
        $box = $this->learnedBox();

        return $this->replace($user, $en, $box, $this->intervals()[$box], ok: 1, known: true);
    }

    /** Takes a word out of the deck: it is "to learn" again. */
    public function forget(User $user, string $en): void
    {
        $this->state->words($user)->get($en)?->delete();
        $this->state->words($user)->forget($en);
    }

    /** The core words of a unit join the deck (due today) when its vocabulary step is done. */
    public function addUnitWords(User $user, string $unitId): void
    {
        $words = $this->state->words($user);

        foreach ($this->content->unitOutline($unitId)['vocab'] ?? [] as $en) {
            if (! $words->has($en)) {
                $this->entry($user, $en, 0, 0)->save();
            }
        }
    }

    /**
     * The words of the levels the placement test skipped that are still "to learn".
     *
     * @return list<array<string, mixed>>
     */
    public function skippedLevelWords(User $user): array
    {
        $start = $this->course->startIndex($user);
        $words = $this->state->words($user);
        $skipped = [];

        foreach ($this->content->words() as $en => $word) {
            if ($this->content->level($word['levelId'])['index'] < $start && ! $words->has((string) $en)) {
                $skipped[] = $word;
            }
        }

        return $skipped;
    }

    /**
     * The extra words of a unit (its word bank), as they are in the word index.
     *
     * @return list<array<string, mixed>>
     */
    public function bankWords(string $unitId): array
    {
        return $this->fromIndex($this->content->unitOutline($unitId)['bank'] ?? []);
    }

    /**
     * Every word a unit teaches: its core words first, then its word bank.
     *
     * @return list<array<string, mixed>>
     */
    public function unitWords(string $unitId): array
    {
        $unit = $this->content->unitOutline($unitId);

        return $unit === null ? [] : $this->unique($this->fromIndex([...$unit['vocab'], ...$unit['bank']]));
    }

    /**
     * Every word of a level, in course order.
     *
     * @return list<array<string, mixed>>
     */
    public function levelWords(string $levelId): array
    {
        $words = [];

        foreach ($this->content->level($levelId)['units'] ?? [] as $unit) {
            $words = [...$words, ...$this->unitWords($unit['id'])];
        }

        return $this->unique($words);
    }

    /**
     * The vocabulary of a level and of every level before it ("knowing the B1 words").
     *
     * @return array{learned: int, learning: int, new: int, total: int}
     */
    public function vocabTo(User $user, string $levelId): array
    {
        $level = $this->content->level($levelId);
        $words = [];

        foreach ($level === null ? [] : array_slice($this->content->levels(), 0, $level['index'] + 1) as $item) {
            $words = [...$words, ...$this->levelWords($item['id'])];
        }

        return $this->counts($user, $this->unique($words));
    }

    /**
     * The words still "to learn" of the units the learner can open in a level, in course order.
     *
     * @return list<array<string, mixed>>
     */
    public function wordsToLearn(User $user, string $levelId): array
    {
        $words = [];

        foreach ($this->content->level($levelId)['units'] ?? [] as $unit) {
            if ($this->course->unitUnlocked($user, $unit['id'])) {
                $words = [...$words, ...$this->unitWords($unit['id'])];
            }
        }

        return array_values(array_filter(
            $this->unique($words),
            fn (array $word): bool => $this->status($user, (string) $word['en']) === WordStatus::New,
        ));
    }

    /** The first level, up to the current one, that still has words to learn in its open units. */
    public function nextWordsLevel(User $user): ?string
    {
        $current = $this->course->currentLevel($user);

        foreach (array_slice($this->content->levels(), 0, ($current['index'] ?? 0) + 1) as $level) {
            if ($this->wordsToLearn($user, $level['id']) !== []) {
                return $level['id'];
            }
        }

        return null;
    }

    /**
     * The words the learner has met (for the games); when there are fewer than $min, the
     * core words of the first three units of the current level instead.
     *
     * @return list<array<string, mixed>>
     */
    public function knownWords(User $user, ?int $min = null): array
    {
        $min ??= (int) config('learning.rules.known_words_min');
        $met = $this->fromIndex(array_values($this->state->words($user)->map(fn (WordReview $review): string => $review->word)->all()));

        if (count($met) >= $min) {
            return $met;
        }

        $level = $this->course->currentLevel($user);
        $words = [];

        foreach (array_slice($level['units'] ?? [], 0, (int) config('learning.rules.known_words_units')) as $unit) {
            $words = [...$words, ...$this->fromIndex($unit['vocab'])];
        }

        return $words;
    }

    /**
     * A word of the index with the learner's state: status, box, due (Y-m-d), known.
     *
     * @param  array<string, mixed>  $word
     * @return array<string, mixed>
     */
    public function withState(User $user, array $word): array
    {
        $review = $this->state->words($user)->get((string) $word['en']);

        return [
            ...$word,
            'status' => $this->status($user, (string) $word['en'])->value,
            'box' => $review?->box,
            'due' => $review?->due_on->toDateString(),
            'known' => (bool) $review?->known,
        ];
    }

    /**
     * Every word that can be in the deck: the course words, then the extra words.
     *
     * @return list<string>
     */
    private function learnable(): array
    {
        return array_map('strval', [...array_keys($this->content->wordLevels()), ...array_keys($this->content->extraWords())]);
    }

    /**
     * The deck entries due today or earlier whose word is in the list, by due date
     * (equal dates keep the order the words joined the deck).
     *
     * @param  list<string>  $inCourse
     * @return list<WordReview>
     */
    private function dueReviews(User $user, array $inCourse): array
    {
        $today = Calendar::todayString();
        $known = array_flip(array_map('strval', $inCourse));
        $due = $this->state->words($user)
            ->filter(fn (WordReview $review): bool => $review->due_on->toDateString() <= $today && isset($known[$review->word]))
            ->values()
            ->all();

        usort($due, fn (WordReview $a, WordReview $b): int => $a->due_on->toDateString() <=> $b->due_on->toDateString());

        return $due;
    }

    /** Puts a whole new entry in place of the old one (a word keeps its place in the deck). */
    private function replace(User $user, string $en, int $box, int $inDays, int $ok, bool $known): WordReview
    {
        $review = $this->state->words($user)->get($en) ?? $this->entry($user, $en, $box, $inDays);
        $review->fill(['box' => $box, 'due_on' => Calendar::inDays($inDays), 'ok' => $ok, 'bad' => 0, 'known' => $known]);
        $review->save();

        return $review;
    }

    /** A new deck entry (not saved yet), already kept in the learner state. */
    private function entry(User $user, string $en, int $box, int $inDays): WordReview
    {
        $review = new WordReview(['word' => $en, 'box' => $box, 'due_on' => Calendar::inDays($inDays), 'ok' => 0, 'bad' => 0, 'known' => false]);
        $review->user_id = $user->id;
        $this->state->words($user)->put($en, $review);

        return $review;
    }

    /**
     * @param  list<string>  $ens
     * @return list<array<string, mixed>>
     */
    private function fromIndex(array $ens): array
    {
        $words = $this->content->words();

        return array_values(array_filter(array_map(fn (string $en): ?array => $words[$en] ?? null, $ens)));
    }

    /**
     * Keeps the first occurrence of each word.
     *
     * @param  list<array<string, mixed>>  $words
     * @return list<array<string, mixed>>
     */
    private function unique(array $words): array
    {
        $seen = [];

        foreach ($words as $word) {
            $seen[(string) $word['en']] ??= $word;
        }

        return array_values($seen);
    }

    private function learnedBox(): int
    {
        return (int) config('learning.rules.learned_box');
    }

    /** @return list<int> */
    private function intervals(): array
    {
        /** @var list<int> */
        return config('learning.rules.intervals');
    }
}
