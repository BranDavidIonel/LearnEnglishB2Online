<?php

use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\WordReviewService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create();
});

it('sends a guest to the login page', function (string $uri) {
    $this->get($uri)->assertRedirect(route('login'));
})->with(['/flashcards', '/words', '/words/learned', '/words/sort/a1', '/units/a1-u01/bank']);

// ---------- my words ----------

it('lists every word of the course with its state, the details following later', function () {
    $this->user->wordReviews()->create(['word' => 'hello', 'box' => 4, 'due_on' => '2026-11-30', 'known' => true]);
    $this->user->wordReviews()->create(['word' => 'goodbye', 'box' => 1, 'due_on' => '2026-11-21']);
    $total = count(app(ContentRepository::class)->words());

    $this->actingAs($this->user)->get(route('words'))->assertInertia(fn (Assert $page) => $page
        ->component('words/Index')
        ->where('tab', 'all')
        ->has('words', $total)
        ->where('words.1', fn ($row) => array_keys($row->all()) === ['en', 'ro', 'ipa', 'emoji', 'levelId', 'status', 'box'])
        ->where('words.2', fn ($row) => array_keys($row->all()) === ['en', 'ro', 'ipa', 'emoji', 'levelId', 'status'])
        ->where('words', fn ($rows) => collect($rows)->firstWhere('en', 'hello') == [
            ...collect($rows)->firstWhere('en', 'hello'), 'status' => 'learned', 'box' => 4, 'known' => true,
        ] && collect($rows)->firstWhere('en', 'goodbye')['status'] === 'learning' && ! isset(collect($rows)->firstWhere('en', 'name')['box']))
        ->where('skipped', null)
        ->where('verbs', count(app(ContentRepository::class)->verbs()))
        ->where('learnedBox', 3)
        ->where('batchSize', 20)
        ->missing('details')
        ->missing('units')
        ->loadDeferredProps('details', fn (Assert $reload) => $reload
            ->has('details', $total)
            ->where('details.hello.unit', 'a1-u01')
            ->has('details.hello.ex')
            ->where('units.a1-u01', ['level' => 'A1', 'order' => 1, 'title' => app(ContentRepository::class)->unitOutline('a1-u01')['title']])));
});

it('opens on a tab and refuses an unknown one', function () {
    $this->actingAs($this->user)->get('/words/new')->assertInertia(fn (Assert $page) => $page->component('words/Index')->where('tab', 'new'));
    $this->actingAs($this->user)->get('/words/everything')->assertNotFound();
});

it('lists the extra words of the learner on a tab of their own, outside the course counts', function () {
    expect(app(ContentRepository::class)->extraWord('the'))->not->toBeNull();
    $this->user->wordReviews()->create(['word' => 'the', 'box' => 1, 'due_on' => '2026-11-22']);
    $this->user->wordReviews()->create(['word' => 'hello', 'box' => 1, 'due_on' => '2026-11-22']);
    $total = count(app(ContentRepository::class)->words());

    $this->actingAs($this->user)->get('/words/extra')->assertInertia(fn (Assert $page) => $page
        ->component('words/Index')
        ->where('tab', 'extra')
        ->has('words', $total)
        ->has('extra', 1)
        ->where('extra.0.en', 'the')
        ->where('extra.0.levelId', null)
        ->where('extra.0.status', 'learning')
        ->where('extra.0.box', 1));
});

it('offers to mark the words of skipped levels as known, until the learner says no', function () {
    $this->user->placement()->create(['start_index' => 2, 'scores' => ['a1' => 1, 'a2' => 0.9, 'b1' => 0.5], 'taken_on' => '2026-11-20']);
    $this->user->wordReviews()->create(['word' => 'hello', 'box' => 6, 'due_on' => '2027-01-20']);
    $skipped = collect(app(ContentRepository::class)->words())->whereIn('levelId', ['a1', 'a2'])->keys()->reject(fn ($en) => $en === 'hello');

    $this->actingAs($this->user)->get(route('words'))->assertInertia(fn (Assert $page) => $page
        ->where('skipped.level', 'a1')
        ->where('skipped.words', $skipped->values()->all()));

    $this->putJson(route('api.flags.update'), ['flag' => 'keepSkippedWords', 'value' => true])->assertOk();

    $this->get(route('words'))->assertInertia(fn (Assert $page) => $page->where('skipped', null));
});

// ---------- quick sort of a level ----------

it('hands out the next 20 words of a level to sort, each with four meanings', function () {
    $todo = app(WordReviewService::class)->wordsToLearn($this->user, 'a1');

    $this->actingAs($this->user)->get(route('words.sort', 'a1'))->assertInertia(fn (Assert $page) => $page
        ->component('words/Sort')
        ->where('level.id', 'a1')
        ->where('level.unlocked', true)
        ->where('stats.learned', 0)
        ->where('stats.total', count(app(WordReviewService::class)->levelWords('a1')))
        ->where('todo.count', count($todo))
        ->has('todo.batch', 20)
        ->where('todo.batch.0.en', $todo[0]['en'])
        ->where('todo.batch.0.status', 'new')
        ->where('todo.batch', fn ($batch) => collect($batch)->every(fn ($word) => count($word['options']) === 4
            && count(array_unique($word['options'])) === 4
            && in_array($word['ro'], $word['options'], true))));
});

it('only counts the words of the open units of a level', function () {
    $this->user->wordReviews()->create(['word' => 'hello', 'box' => 3, 'due_on' => '2026-11-28']);

    $this->actingAs($this->user)->get(route('words.sort', 'a1'))->assertInertia(fn (Assert $page) => $page
        ->where('todo.count', count(app(WordReviewService::class)->wordsToLearn($this->user->fresh(), 'a1')))
        ->where('todo.batch', fn ($batch) => ! collect($batch)->contains('en', 'hello'))
        ->where('stats.learned', 1));
});

it('locks the words of a level that is not open yet', function () {
    $this->actingAs($this->user)->get(route('words.sort', 'b1'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('words/Sort')
        ->where('level.unlocked', false)
        ->where('stats', null)
        ->where('todo', null));
});

it('refuses an unknown level', function () {
    $this->actingAs($this->user)->get('/words/sort/c1')->assertNotFound();
});

// ---------- word bank of a unit ----------

it('shows the word bank of a unit with the next batch', function () {
    $bank = app(WordReviewService::class)->bankWords('a1-u01');
    $this->user->wordReviews()->create(['word' => $bank[0]['en'], 'box' => 0, 'due_on' => '2026-11-21']);
    $this->user->unitProgress()->create(['unit' => 'a1-u01', 'steps' => ['vocab', 'grammar'], 'best' => 0, 'passed' => false, 'review' => false, 'tries' => 0]);

    $this->actingAs($this->user)->get(route('units.bank', 'a1-u01'))->assertInertia(fn (Assert $page) => $page
        ->component('words/Bank')
        ->where('unit.id', 'a1-u01')
        ->where('unit.steps', ['vocab' => true, 'grammar' => true])
        ->where('unit.passed', false)
        ->where('unit.vocab', count(app(ContentRepository::class)->unitOutline('a1-u01')['vocab']))
        ->where('level', ['id' => 'a1', 'name' => 'A1', 'color' => '#22c55e'])
        ->has('words', count($bank))
        ->where('words.0.status', 'learning')
        ->where('todo.count', count($bank) - 1)
        ->has('todo.batch', min(20, count($bank) - 1))
        ->where('todo.batch.0.en', $bank[1]['en'])
        ->has('choices.'.$bank[0]['en'], 3)
        ->has('units.a1-u01'));
});

it('shows the unit screens for a unit that is missing or not open yet', function () {
    $this->actingAs($this->user)->get('/units/a1-u99/bank')->assertInertia(fn (Assert $page) => $page
        ->component('units/Unavailable')
        ->where('reason', 'missing'));

    $this->get('/units/a1-u05/bank')->assertInertia(fn (Assert $page) => $page
        ->component('units/Unavailable')
        ->where('reason', 'locked'));
});

// ---------- flashcards ----------

it('has no flashcards for a new learner', function () {
    $this->actingAs($this->user)->get(route('flashcards'))->assertInertia(fn (Assert $page) => $page
        ->component('words/Flashcards')
        ->where('inDeck', 0)
        ->where('dueCount', 0)
        ->where('due', [])
        ->where('counts.learned', 0)
        ->has('practice', 20));
});

it('brings the cards due today, the most overdue first', function () {
    $this->user->wordReviews()->create(['word' => 'hello', 'box' => 1, 'due_on' => '2026-11-21']);
    $this->user->wordReviews()->create(['word' => 'goodbye', 'box' => 2, 'due_on' => '2026-11-19']);
    $this->user->wordReviews()->create(['word' => 'name', 'box' => 4, 'due_on' => '2026-12-10']);
    $this->user->wordReviews()->create(['word' => 'a word that left the course', 'box' => 1, 'due_on' => '2026-11-01']);

    $this->actingAs($this->user)->get(route('flashcards'))->assertInertia(fn (Assert $page) => $page
        ->where('inDeck', 4)
        ->where('dueCount', 2)
        ->has('due', 2)
        ->where('due.0.en', 'goodbye')
        ->where('due.1.en', 'hello')
        ->has('due.0.exRo')
        ->where('counts.learning', 2)
        ->where('counts.learned', 1)
        // Fewer than 8 words met: free practice takes the first words of the current level.
        ->has('practice', 20));
});

it('takes at most 30 cards into a review', function () {
    foreach (array_slice(array_keys(app(ContentRepository::class)->words()), 0, 35) as $en) {
        $this->user->wordReviews()->create(['word' => $en, 'box' => 0, 'due_on' => '2026-11-21']);
    }

    $this->actingAs($this->user)->get(route('flashcards'))->assertInertia(fn (Assert $page) => $page
        ->where('dueCount', 35)
        ->has('due', 30)
        ->has('practice', 20));
});
