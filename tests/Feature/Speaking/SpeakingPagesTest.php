<?php

use App\Models\User;
use App\Services\Content\ContentRepository;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('sends a guest to the login page', function (string $uri) {
    auth()->logout();

    $this->get($uri)->assertRedirect(route('login'));
})->with(['/speaking', '/speaking/talk', '/speaking/role/a1-u01', '/speaking/sprint']);

it('does not know other modes', function () {
    $this->get('/speaking/sing')->assertNotFound();
});

it('shows the hub of a new learner', function () {
    $unit = app(ContentRepository::class)->unitOutline('a1-u01');

    $this->get(route('speaking'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('speaking/Index')
        ->where('current', 'a1')
        ->where('stats', ['said' => 0, 'talks' => 0, 'best' => []])
        ->has('levels', 4)
        ->where('levels.0.id', 'a1')
        ->where('levels.0.unlocked', true)
        ->where('levels.0.scenarios', 8)
        ->where('levels.0.talked', 0)
        ->where('levels.0.units', [[
            'id' => 'a1-u01', 'order' => 1, 'emoji' => $unit['emoji'], 'title' => $unit['title'],
            'dialogue' => 'La un eveniment pentru programatori', 'score' => null,
        ]])
        ->where('levels.1.unlocked', false)
        ->where('levels.1.units', []));
});

it('shows the learner\'s speaking results on the hub', function () {
    $this->user->profile()->create(['spoken' => 7]);
    $this->user->speakingScores()->create(['scenario' => 'a1-s01', 'score' => 0.9]);
    $this->user->speakingScores()->create(['scenario' => 'a1-s02', 'score' => 0.4]);
    $this->user->speakingScores()->create(['scenario' => 'role-a1-u01', 'score' => 0.5]);
    $this->user->bestScores()->create(['area' => 'speaking', 'game' => 'sprint', 'value' => 12]);
    $this->user->bestScores()->create(['area' => 'speaking', 'game' => 'pairs', 'value' => 6]);

    $this->get(route('speaking'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.said', 7)
        ->where('stats.talks', 3)
        ->where('stats.best', ['sprint' => 12, 'pairs' => 6])
        ->where('levels.0.talked', 2)
        ->where('levels.0.units.0.score', 0.5));
});

it('opens every unit of the levels the placement test skipped', function () {
    $this->user->placement()->create(['start_index' => 2, 'scores' => ['a1' => 0.9, 'a2' => 0.8, 'b1' => 0.3], 'taken_on' => '2026-11-01']);

    $this->get(route('speaking'))->assertInertia(fn (Assert $page) => $page
        ->where('current', 'b1')
        ->where('levels.0.unlocked', true)
        ->has('levels.0.units', 10)
        ->where('levels.2.unlocked', true)
        ->has('levels.2.units', 1)
        ->where('levels.3.unlocked', false));
});

it('lists the conversations of the level asked for while it is open', function () {
    $this->user->placement()->create(['start_index' => 2, 'scores' => ['a1' => 0.9, 'a2' => 0.8, 'b1' => 0.3], 'taken_on' => '2026-11-01']);
    $this->user->speakingScores()->create(['scenario' => 'a2-s01', 'score' => 0.9]);

    $this->get(route('speaking', ['mode' => 'talk', 'level' => 'a2']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('speaking/Talk')
        ->where('level', 'a2')
        ->where('open', ['a1', 'a2', 'b1'])
        ->has('scenarios', 8)
        ->where('scenarios.0.id', 'a2-s01')
        ->where('scenarios.0.score', 0.9)
        ->where('scenarios.1.score', null)
        ->has('scenarios.0.lines')
        ->missing('scenario'));
});

it('falls back to the level being worked on for a locked or unknown level', function (string $asked) {
    $this->get(route('speaking', ['mode' => 'talk', 'level' => $asked]))->assertInertia(fn (Assert $page) => $page
        ->where('level', 'a1')
        ->where('open', ['a1']));
})->with(['b2', 'zz']);

it('plays a conversation of any level', function () {
    $this->get(route('speaking', ['mode' => 'talk', 'arg' => 'b2-s01']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('speaking/Talk')
        ->where('scenario.id', 'b2-s01')
        ->has('scenario.turns')
        ->has('scenario.partner'));
});

it('goes back to the list for an unknown conversation', function () {
    $this->get(route('speaking', ['mode' => 'talk', 'arg' => 'a1-s99']))->assertRedirect(route('speaking', ['mode' => 'talk', 'level' => 'a1']));
});

it('plays the dialogue of an open unit', function () {
    $this->get(route('speaking', ['mode' => 'role', 'arg' => 'a1-u01']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('speaking/Role')
        ->where('unit.id', 'a1-u01')
        ->where('unit.dialogue.titleRo', 'La un eveniment pentru programatori')
        ->has('unit.dialogue.lines', 8));
});

it('sends a locked or unknown unit back to the hub', function (string $unit) {
    $this->get(route('speaking', ['mode' => 'role', 'arg' => $unit]))->assertRedirect(route('speaking'));
})->with(['a1-u02', 'b2-u01', 'a1-u99']);

it('sends a role-play without a unit back to the hub', function () {
    $this->get(route('speaking', ['mode' => 'role']))->assertRedirect(route('speaking'));
});

it('lists the phrase topics and the lessons to repeat', function () {
    $this->get(route('speaking', ['mode' => 'repeat']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('speaking/Sentences')
        ->where('mode', 'repeat')
        ->where('level', 'a1')
        ->has('topics', 6)
        ->has('topics.0', fn (Assert $topic) => $topic->hasAll(['emoji', 'topic', 'topicRo']))
        ->where('lessons', true)
        ->where('round', null));
});

it('gives a round of a phrase topic', function () {
    $topic = app(ContentRepository::class)->speaking('a1')['phrases'][1];

    $this->get(route('speaking', ['mode' => 'repeat', 'arg' => '1']))->assertInertia(fn (Assert $page) => $page
        ->where('round.kind', 'topic')
        ->where('round.title', "{$topic['emoji']} {$topic['topic']}")
        ->has('round.items', count($topic['items'])));
});

it('gives a round of the lines of the open lessons', function () {
    $this->get(route('speaking', ['mode' => 'repeat', 'arg' => 'lessons']))->assertInertia(fn (Assert $page) => $page
        ->where('round.kind', 'lessons')
        ->has('round.items', 8)
        ->has('round.items.0', fn (Assert $line) => $line->hasAll(['en', 'ro'])));
});

it('goes back to the topics for an unknown topic', function (string $topic) {
    $this->get(route('speaking', ['mode' => 'repeat', 'arg' => $topic]))->assertRedirect(route('speaking', ['mode' => 'repeat', 'level' => 'a1']));
})->with(['99', 'abc']);

it('gives short sentences of the topics and the open units to say in English', function () {
    $this->get(route('speaking', ['mode' => 'say']))->assertOk()->assertInertia(function (Assert $page) {
        $page->component('speaking/Sentences')->where('mode', 'say')->where('round.kind', 'say');
        $items = collect($page->toArray()['props']['round']['items']);

        expect($items)->not->toBeEmpty()
            ->and($items->every(fn (array $item): bool => count(preg_split('/\s+/', $item['en'])) <= 12))->toBeTrue()
            ->and($items->pluck('en'))->toContain("Hello! I'm Andrei.");
    });
});

it('gives the open questions of the level', function () {
    $this->get(route('speaking', ['mode' => 'answer']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('speaking/Answer')
        ->has('questions', 12)
        ->has('questions.0', fn (Assert $q) => $q->hasAll(['q', 'qRo', 'starters', 'sample'])));
});

it('shows the empty state for a level without speaking content', function () {
    $content = app(ContentRepository::class);
    $this->instance(ContentRepository::class, Mockery::mock($content)->shouldReceive('speaking')->andReturnNull()->getMock());

    $this->get(route('speaking', ['mode' => 'answer']))->assertInertia(fn (Assert $page) => $page->where('questions', null));
    $this->get(route('speaking', ['mode' => 'talk']))->assertInertia(fn (Assert $page) => $page->where('scenarios', null));
    $this->get(route('speaking', ['mode' => 'repeat']))->assertInertia(fn (Assert $page) => $page->where('topics', [])->where('lessons', true));
    $this->get(route('speaking'))->assertInertia(fn (Assert $page) => $page->where('levels.0.scenarios', null));
});

it('opens the sound-alike words', function () {
    $this->get(route('speaking', ['mode' => 'pairs']))->assertOk()->assertInertia(fn (Assert $page) => $page->component('speaking/Pairs'));
});

it('gives the voice sprint the words the learner has met, short ones only', function () {
    $this->get(route('speaking', ['mode' => 'sprint']))->assertOk()->assertInertia(function (Assert $page) {
        $page->component('speaking/Sprint')->has('words.0', fn (Assert $word) => $word->hasAll(['en', 'ro', 'emoji']));
        $words = collect($page->toArray()['props']['words']);

        expect($words->pluck('en'))->toContain('hello')
            ->and($words->every(fn (array $w): bool => count(preg_split('/\s+/', $w['en'])) <= 2 && mb_strlen($w['en']) <= 16))->toBeTrue()
            ->and($words->pluck('en')->unique()->count())->toBe($words->count());
    });
});

it('gives the voice sprint the words of the levels below the current one', function () {
    $this->user->placement()->create(['start_index' => 1, 'scores' => ['a1' => 0.9, 'a2' => 0.3], 'taken_on' => '2026-11-01']);
    $this->user->wordReviews()->create(['word' => 'customer', 'box' => 3, 'due_on' => '2026-11-28']);

    $this->get(route('speaking', ['mode' => 'sprint']))->assertInertia(function (Assert $page) {
        $words = collect($page->toArray()['props']['words']);
        $levels = app(ContentRepository::class)->wordLevels();

        expect($words->count())->toBeGreaterThan(20)
            ->and($words->every(fn (array $w): bool => $levels[$w['en']] === 'a1' || $w['en'] === 'customer'))->toBeTrue();
    });
});
