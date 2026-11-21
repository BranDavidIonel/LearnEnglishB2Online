<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('sends a guest to the login page', function () {
    $this->get(route('games'))->assertRedirect(route('login'));
    $this->get(route('games', 'memory'))->assertRedirect(route('login'));
});

it('lists the games with the records', function () {
    $this->actingAs($this->user)->get(route('games'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('games/Index')
        ->where('records', []));

    $this->user->bestScores()->create(['area' => 'games', 'game' => 'memory', 'value' => 14]);
    $this->user->bestScores()->create(['area' => 'games', 'game' => 'sprint', 'value' => 23]);
    $this->user->bestScores()->create(['area' => 'speaking', 'game' => 'sprint', 'value' => 9]);

    $this->actingAs($this->user)->get(route('games'))->assertInertia(fn (Assert $page) => $page
        ->where('records', ['memory' => 14, 'sprint' => 23]));
});

it('sends the records as an object even when there is none', function () {
    $page = $this->actingAs($this->user)->get(route('games'))->viewData('page');

    expect($page['props']['records'])->toBeInstanceOf(stdClass::class);
});

it('opens each game with the words it plays with', function (string $game, string $component) {
    $this->actingAs($this->user)->get(route('games', $game))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component($component)
        ->has('words.0', fn (Assert $word) => $word->hasAll(['en', 'ro', 'emoji'])));
})->with([
    ['memory', 'games/Memory'],
    ['sprint', 'games/Sprint'],
    ['spell', 'games/Spell'],
]);

it('plays with the words met, or with the first units of the current level when there are too few', function () {
    // A new learner: the core words of the first three units of A1.
    $this->actingAs($this->user)->get(route('games', 'sprint'))->assertInertia(fn (Assert $page) => $page
        ->where('words.0.en', fn (string $en) => $en !== '')
        ->has('words', fn (Assert $words) => $words->etc()));

    foreach (['hello', 'goodbye', 'name', 'thank you', 'please', 'sorry', 'yes', 'no'] as $word) {
        $this->user->wordReviews()->create(['word' => $word, 'due_on' => '2026-11-21']);
    }

    $this->actingAs($this->user)->get(route('games', 'memory'))->assertInertia(fn (Assert $page) => $page
        ->has('words', 8)
        ->where('words.0', fn ($word) => $word['en'] === 'hello' && $word['ro'] !== ''));
});

it('leaves long phrases out of the dictation', function () {
    $this->user->wordReviews()->create(['word' => 'how are you?', 'due_on' => '2026-11-21']);

    $this->actingAs($this->user)->get(route('games', 'spell'))->assertInertia(fn (Assert $page) => $page
        ->where('words', fn ($words) => collect($words)->every(fn (array $word) => ! preg_match('/\s/', $word['en']) || mb_strlen($word['en']) < 18)));
});

it('answers 404 for a game that does not exist', function () {
    $this->actingAs($this->user)->get('/games/chess')->assertNotFound();
});
