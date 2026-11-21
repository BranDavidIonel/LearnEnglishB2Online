<?php

use App\Models\User;
use App\Services\Content\ContentRepository;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('grades words, with spaces, apostrophes and question marks in the body', function () {
    $this->postJson(route('api.words.grade'), ['words' => ['how are you?', 'hello'], 'grade' => 2])
        ->assertOk()
        ->assertJsonCount(2, 'words')
        ->assertJsonPath('words.0.en', 'how are you?')
        ->assertJsonPath('words.0.status', 'learning')
        ->assertJsonPath('words.0.box', 1)
        ->assertJsonPath('words.0.due', '2026-11-22')
        ->assertJsonPath('words.0.unit', 'a1-u01')
        ->assertJsonPath('words.0.levelId', 'a1')
        ->assertJsonPath('words.0.bank', false)
        ->assertJsonStructure(['words' => [['en', 'ro', 'status', 'box', 'due', 'known', 'unit', 'levelId', 'bank']], 'learner']);

    expect($this->user->wordReviews()->count())->toBe(2);
});

it('marks words known, sorted, to learn and takes them out', function () {
    $this->postJson(route('api.words.known'), ['words' => ['hello', 'goodbye']])
        ->assertJsonPath('words.1.status', 'learned')
        ->assertJsonPath('words.1.known', true)
        ->assertJsonPath('words.1.box', 6);

    $this->postJson(route('api.words.sorted'), ['words' => ['name']])->assertJsonPath('words.0.box', 3)->assertJsonPath('words.0.due', '2026-11-28');
    $this->postJson(route('api.words.learn'), ['words' => ['hello']])->assertJsonPath('words.0.status', 'learning')->assertJsonPath('learner.due.words', 1);
    $this->postJson(route('api.words.forget'), ['words' => ['hello']])->assertJsonPath('words.0.status', 'new')->assertJsonPath('words.0.box', null);

    expect($this->user->wordReviews()->pluck('word')->sort()->values()->all())->toBe(['goodbye', 'name']);
});

it('marks a long list known in one call', function () {
    $words = array_slice(array_keys(app(ContentRepository::class)->words()), 0, 600);

    $this->postJson(route('api.words.known'), ['words' => $words])->assertOk()->assertJsonCount(600, 'words');

    expect($this->user->wordReviews()->where('known', true)->count())->toBe(600);
});

it('validates the words', function () {
    $this->postJson(route('api.words.known'), ['words' => ['not a word of the course']])->assertJsonValidationErrors('words.0');
    $this->postJson(route('api.words.known'), ['words' => []])->assertJsonValidationErrors('words');
    $this->postJson(route('api.words.known'), ['words' => ['hello', 'hello']])->assertJsonValidationErrors('words.0');
    $this->postJson(route('api.words.grade'), ['words' => ['hello'], 'grade' => 3])->assertJsonValidationErrors('grade');
    $this->postJson(route('api.words.grade'), ['words' => ['hello']])->assertJsonValidationErrors('grade');
    $this->postJson(route('api.words.learn'), ['words' => ['hello'], 'grade' => 2])->assertJsonValidationErrors('grade');
});

it('grades, marks, learns and forgets verbs', function () {
    $this->postJson(route('api.verbs.grade'), ['verbs' => ['be', 'go'], 'grade' => 2])
        ->assertOk()
        ->assertJsonPath('verbs.0.v1', 'be')
        ->assertJsonPath('verbs.0.rank', 1)
        ->assertJsonPath('verbs.0.box', 1)
        ->assertJsonStructure(['verbs' => [['v1', 'v2', 'v3', 'ro', 'rank', 'levelId', 'status', 'box', 'due', 'known']], 'learner']);

    $this->postJson(route('api.verbs.known'), ['verbs' => ['feel']])->assertJsonPath('verbs.0.status', 'learned');
    $this->postJson(route('api.verbs.learn'), ['verbs' => ['feel']])->assertJsonPath('verbs.0.box', 0)->assertJsonPath('learner.due.verbs', 1);
    $this->postJson(route('api.verbs.forget'), ['verbs' => ['feel']])->assertJsonPath('verbs.0.status', 'new');

    $this->postJson(route('api.verbs.grade'), ['verbs' => ['be'], 'grade' => 1])->assertJsonValidationErrors('grade');
    $this->postJson(route('api.verbs.known'), ['verbs' => ['swim-ish']])->assertJsonValidationErrors('verbs.0');
});
