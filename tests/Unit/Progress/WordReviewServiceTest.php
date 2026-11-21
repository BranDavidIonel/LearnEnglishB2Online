<?php

use App\Enums\WordStatus;
use App\Services\Content\ContentRepository;
use App\Services\Progress\WordReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->words = app(WordReviewService::class);
    $this->content = app(ContentRepository::class);
    $this->user = freshLearner();
});

/** The state of a word in the deck: [box, due, ok, bad, known]. */
function wordDeckState(string $en): array
{
    $review = test()->user->wordReviews()->where('word', $en)->sole();

    return [$review->box, $review->due_on->toDateString(), $review->ok, $review->bad, $review->known];
}

it('moves a word through the boxes', function () {
    $this->words->grade($this->user, 'hello', 2);
    expect(wordDeckState('hello'))->toBe([1, '2026-11-22', 1, 0, false])
        ->and($this->words->status($this->user, 'hello'))->toBe(WordStatus::Learning);

    $this->words->grade($this->user, 'hello', 2);
    $this->words->grade($this->user, 'hello', 2);
    expect(wordDeckState('hello'))->toBe([3, '2026-11-28', 3, 0, false])
        ->and($this->words->status($this->user, 'hello'))->toBe(WordStatus::Learned);

    foreach (range(1, 5) as $ignored) {
        $this->words->grade($this->user, 'hello', 2);
    }
    expect(wordDeckState('hello'))->toBe([6, '2027-01-20', 8, 0, false]); // never past the last box (60 days)
});

it('keeps a hard word at least in box 1, for tomorrow', function () {
    $this->words->grade($this->user, 'hello', 1);
    expect(wordDeckState('hello'))->toBe([1, '2026-11-22', 1, 0, false]);

    $this->words->grade($this->user, 'hello', 2);
    $this->words->grade($this->user, 'hello', 2);
    $this->words->grade($this->user, 'hello', 1);
    expect(wordDeckState('hello'))->toBe([3, '2026-11-22', 4, 0, false]);
});

it('sends a forgotten word back to box 0, again today', function () {
    $this->words->markKnown($this->user, 'hello');
    $this->words->grade($this->user, 'hello', 0);

    expect(wordDeckState('hello'))->toBe([0, '2026-11-21', 0, 1, true]);
});

it('marks a word known, sorted known, to learn, or takes it out of the deck', function () {
    $this->words->markKnown($this->user, 'hello');
    expect(wordDeckState('hello'))->toBe([6, '2027-01-20', 0, 0, true]);

    $this->words->sortedKnown($this->user, 'goodbye');
    expect(wordDeckState('goodbye'))->toBe([3, '2026-11-28', 1, 0, true]);

    $this->words->grade($this->user, 'hello', 0);
    $this->words->learn($this->user, 'hello');
    expect(wordDeckState('hello'))->toBe([0, '2026-11-21', 0, 0, false]);

    $this->words->forget($this->user, 'hello');
    expect($this->words->status($this->user, 'hello'))->toBe(WordStatus::New)
        ->and($this->user->wordReviews()->count())->toBe(1);
});

it('keeps the place of a word in the deck when it is replaced', function () {
    $this->words->learn($this->user, 'hello');
    $this->words->learn($this->user, 'goodbye');
    $this->words->markKnown($this->user, 'hello');

    expect($this->user->wordReviews()->orderBy('id')->pluck('word')->all())->toBe(['hello', 'goodbye']);
});

it('lists the words due today, most overdue first, only words still in the course', function () {
    $this->user->wordReviews()->createMany([
        ['word' => 'goodbye', 'box' => 1, 'due_on' => '2026-11-21'],
        ['word' => 'hello', 'box' => 2, 'due_on' => '2026-11-19'],
        ['word' => 'name', 'box' => 2, 'due_on' => '2026-11-21'],
        ['word' => 'fine', 'box' => 2, 'due_on' => '2026-11-22'],
        ['word' => 'a word that left the course', 'box' => 0, 'due_on' => '2026-11-01'],
    ]);

    expect(array_column($this->words->due($this->user), 'en'))->toBe(['hello', 'goodbye', 'name'])
        ->and($this->words->due($this->user)[0])->toBe($this->content->word('hello'))
        ->and($this->words->dueCount($this->user))->toBe(3);
});

it('counts learned, learning and new words', function () {
    $this->words->markKnown($this->user, 'hello');
    $this->words->learn($this->user, 'goodbye');
    $this->words->learn($this->user, 'a word that left the course');

    expect($this->words->counts($this->user))->toBe(['learned' => 1, 'learning' => 1, 'new' => 2944, 'total' => 2946])
        ->and($this->words->counts($this->user, ['hello', 'name']))->toBe(['learned' => 1, 'learning' => 0, 'new' => 1, 'total' => 2]);
});

it('gives the words of a unit, its bank and its level, each word once', function () {
    $outline = $this->content->unitOutline('a1-u01');
    $unitWords = $this->words->unitWords('a1-u01');
    $levelWords = $this->words->levelWords('a1');

    expect(array_column($this->words->bankWords('a1-u01'), 'en'))->toBe(array_values(array_filter($outline['bank'], fn ($en) => $this->content->word($en) !== null)))
        ->and(array_slice(array_column($unitWords, 'en'), 0, count($outline['vocab'])))->toBe($outline['vocab'])
        ->and(array_column($unitWords, 'en'))->toBe(array_values(array_unique(array_column($unitWords, 'en'))))
        ->and(array_column($levelWords, 'en'))->toBe(array_values(array_unique(array_column($levelWords, 'en'))))
        ->and($this->words->unitWords('z1-u01'))->toBe([]);

    $total = 0;
    foreach (['a1', 'a2', 'b1', 'b2'] as $level) {
        $total += count($this->words->levelWords($level));
    }
    // A word met in an earlier level counts in both levels, like the original.
    expect($total)->toBeGreaterThanOrEqual(2946)
        ->and($this->words->vocabTo($this->user, 'b2')['total'])->toBe(2946)
        ->and($this->words->vocabTo($this->user, 'a1')['total'])->toBe(count($levelWords))
        ->and($this->words->vocabTo($this->user, 'c1')['total'])->toBe(0);
});

it('offers the words of the open units still to learn, and the next level with some', function () {
    $first = $this->words->wordsToLearn($this->user, 'a1');

    expect(array_column($first, 'en'))->toBe(array_column($this->words->unitWords('a1-u01'), 'en'))
        ->and($this->words->wordsToLearn($this->user, 'a2'))->toBe([])
        ->and($this->words->nextWordsLevel($this->user))->toBe('a1');

    foreach ($first as $word) {
        $this->words->markKnown($this->user, $word['en']);
    }

    expect($this->words->wordsToLearn($this->user, 'a1'))->toBe([])
        ->and($this->words->nextWordsLevel($this->user))->toBeNull();

    passUnits($this->user, ['a1-u01']);
    expect($this->words->nextWordsLevel($this->user))->toBe('a1')
        ->and($this->words->wordsToLearn($this->user, 'a1')[0]['unit'])->toBe('a1-u02');
});

it('lists the words of the skipped levels still to learn', function () {
    expect($this->words->skippedLevelWords($this->user))->toBe([]);

    placeAt($this->user, 1);
    $this->words->markKnown($this->user, 'hello');
    $skipped = $this->words->skippedLevelWords($this->user);

    expect($skipped)->toHaveCount(count(array_filter($this->content->wordLevels(), fn ($level) => $level === 'a1')) - 1)
        ->and(array_column($skipped, 'en'))->not->toContain('hello')
        ->and(array_unique(array_column($skipped, 'levelId')))->toBe(['a1']);
});

it('gives the games the words met, or the first units of the current level', function () {
    $fallback = $this->words->knownWords($this->user);
    $expected = [];
    foreach (array_slice($this->content->level('a1')['units'], 0, 3) as $unit) {
        $expected = [...$expected, ...$unit['vocab']];
    }

    expect(array_column($fallback, 'en'))->toBe($expected);

    foreach (['hello', 'goodbye', 'name', 'fine', 'developer', 'teacher', 'doctor', 'student', 'a word that left the course'] as $en) {
        $this->words->learn($this->user, $en);
    }

    expect(array_column($this->words->knownWords($this->user), 'en'))->toBe(['hello', 'goodbye', 'name', 'fine', 'developer', 'teacher', 'doctor', 'student'])
        ->and($this->words->knownWords($this->user, 9))->toHaveCount(count($expected));
});

it('adds the learner state to a word', function () {
    $this->words->sortedKnown($this->user, 'hello');

    expect($this->words->withState($this->user, $this->content->word('hello')))
        ->toMatchArray(['en' => 'hello', 'unit' => 'a1-u01', 'levelId' => 'a1', 'status' => 'learned', 'box' => 3, 'due' => '2026-11-28', 'known' => true])
        ->and($this->words->withState($this->user, $this->content->word('goodbye')))
        ->toMatchArray(['status' => 'new', 'box' => null, 'due' => null, 'known' => false]);
});
