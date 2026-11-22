<?php

use App\Models\User;
use App\Services\Content\ContentFiles;
use App\Services\Content\ContentRepository;
use App\Services\Progress\BadgeService;
use App\Services\Progress\LearnerState;
use App\Services\Progress\ProgressExporter;
use App\Services\Progress\ProgressImporter;
use App\Services\Progress\WordReviewService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

/*
| Extra words: glossary words the learner puts into the flashcards. They are learned like
| course words but never change the course numbers. "start" and "system" are glossary words,
| "Cluj" a name.
*/

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/** The course numbers the extra words must never change. */
function courseNumbers(User $user): array
{
    app(LearnerState::class)->flush();
    $words = app(WordReviewService::class);

    return [
        $words->counts($user),
        $words->vocabTo($user, 'b2'),
        $words->counts($user, $words->levelWords('a1')),
        count($words->wordsToLearn($user, 'a1')),
        array_column(app(BadgeService::class)->badges($user), 'ok'),
    ];
}

it('learns a glossary word like a course word', function () {
    expect(app(ContentRepository::class)->extraWord('start'))->not->toBeNull();

    $this->postJson(route('api.words.learn'), ['words' => ['start']])
        ->assertOk()
        ->assertJsonPath('words.0.en', 'start')
        ->assertJsonPath('words.0.extra', true)
        ->assertJsonPath('words.0.levelId', null)
        ->assertJsonPath('words.0.unit', null)
        ->assertJsonPath('words.0.status', 'learning')
        ->assertJsonPath('learner.due.words', 1);

    $this->postJson(route('api.words.grade'), ['words' => ['start'], 'grade' => 2])->assertJsonPath('words.0.box', 1);
    $this->postJson(route('api.words.known'), ['words' => ['system']])->assertJsonPath('words.0.status', 'learned');
    $this->postJson(route('api.words.forget'), ['words' => ['system']])->assertJsonPath('words.0.status', 'new');
});

it('never changes the course numbers', function () {
    $before = courseNumbers($this->user);

    $this->postJson(route('api.words.known'), ['words' => ['start', 'system']])->assertOk();
    $this->postJson(route('api.words.learn'), ['words' => ['hello']])->assertOk();

    $after = courseNumbers($this->user);
    $this->postJson(route('api.words.forget'), ['words' => ['hello']])->assertOk();

    expect($after[0]['learning'])->toBe($before[0]['learning'] + 1)
        ->and(courseNumbers($this->user))->toBe($before)
        ->and($this->user->snapshots()->latest('id')->first()->words)->toBe(0);
});

it('shows a due extra word on the flashcards and lists the extra words', function () {
    $this->postJson(route('api.words.learn'), ['words' => ['start']])->assertOk();
    $words = app(WordReviewService::class);
    app(LearnerState::class)->flush();

    expect($words->dueCount($this->user))->toBe(1)
        ->and($words->due($this->user)[0])->toMatchArray(['en' => 'start', 'extra' => true, 'ex' => null, 'levelId' => null])
        ->and(array_column($words->extraWords($this->user), 'en'))->toBe(['start'])
        ->and($words->extraWords($this->user)[0]['status'])->toBe('learning');
});

it('refuses names and unknown words', function () {
    $this->postJson(route('api.words.learn'), ['words' => ['Cluj']])->assertJsonValidationErrors('words.0');
    $this->postJson(route('api.words.learn'), ['words' => ['zzzz']])->assertJsonValidationErrors('words.0');
});

it('keeps extra words through export and import', function () {
    $this->postJson(route('api.words.known'), ['words' => ['start']])->assertOk();
    $exported = app(ProgressExporter::class)->toJson($this->user);

    $other = User::factory()->create();
    app(ProgressImporter::class)->importJson($other, $exported);

    expect($other->wordReviews()->where('word', 'start')->value('known'))->toBeTrue();
});

it('looks up a glossary word, a name, an expression and a word found nowhere', function () {
    $this->postJson(route('api.words.learn'), ['words' => ['start']])->assertOk();

    $this->getJson(route('api.words.lookup', ['word' => 'started']))
        ->assertOk()
        ->assertJsonPath('lookup.source', 'glossary')
        ->assertJsonPath('lookup.base', 'start')
        ->assertJsonPath('lookup.relation', 'past')
        ->assertJsonPath('lookup.entry.levelId', null)
        ->assertJsonPath('lookup.state.status', 'learning');

    $this->getJson(route('api.words.lookup', ['word' => "Andrei's"]))
        ->assertJsonPath('lookup.source', 'name')
        ->assertJsonPath('lookup.relation', 'possessive')
        ->assertJsonPath('lookup.state', null);

    $this->getJson(route('api.words.lookup', ['word' => 'looking', 'sentence' => "I'm looking forward to the trip."]))
        ->assertJsonPath('lookup.base', 'look')
        ->assertJsonPath('lookup.relation', 'ing')
        ->assertJsonPath('lookup.state.box', 0)
        ->assertJsonFragment(['en' => 'look forward to']);

    $this->getJson(route('api.words.lookup', ['word' => 'zzzz']))
        ->assertJsonPath('lookup.found', false)
        ->assertJsonPath('lookup.entry', null)
        ->assertJsonPath('lookup.phrases', []);

    $this->getJson(route('api.words.lookup'))->assertJsonValidationErrors('word');
    expect($this->user->wordReviews()->count())->toBe(1);
});

it('looks a word that starts the sentence up in lower case first', function () {
    $this->getJson(route('api.words.lookup', ['word' => 'May', 'sentence' => 'May I come in?']))
        ->assertJsonPath('lookup.source', 'glossary')
        ->assertJsonPath('lookup.base', 'may');

    $this->getJson(route('api.words.lookup', ['word' => 'May', 'sentence' => 'We start in May.']))
        ->assertJsonPath('lookup.source', 'course')
        ->assertJsonPath('lookup.base', 'May')
        ->assertJsonPath('lookup.entry.levelId', 'a1');

    $this->getJson(route('api.words.lookup', ['word' => 'May']))->assertJsonPath('lookup.base', 'May');
});

it('shows the course word for a form the glossary only maps', function () {
    $this->getJson(route('api.words.lookup', ['word' => 'lying']))
        ->assertJsonPath('lookup.source', 'course')
        ->assertJsonPath('lookup.base', 'lie')
        ->assertJsonPath('lookup.relation', 'ing')
        ->assertJsonPath('lookup.state.status', 'new');
});

it('reads the glossary: extra words leave out the course words, a missing file is empty', function () {
    $content = app(ContentRepository::class);
    $none = new ContentRepository(new ContentFiles(base_path('tests/Fixtures/content/none')), new Repository(new ArrayStore), 0);

    expect($content->extraWord('start'))->toMatchArray(['en' => 'start', 'pos' => 'verb'])
        ->and($content->extraWord('child'))->toBeNull()
        ->and(array_column($content->glossary()['words'], 'en'))->toContain('child')
        ->and($none->glossary())->toBe(['words' => [], 'names' => []])
        ->and($none->extraWords())->toBe([]);
});

it('refuses a guest', function () {
    auth()->logout();

    $this->get(route('api.words.lookup', ['word' => 'went']))->assertUnauthorized();
});
