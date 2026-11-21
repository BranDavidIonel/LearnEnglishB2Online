<?php

use App\Models\User;
use App\Services\Content\ContentRepository;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->unit = app(ContentRepository::class)->unit('a1-u01');
});

it('sends guests to the login page', function (string $uri) {
    auth()->logout();

    $this->get($uri)->assertRedirect(route('login'));
})->with(['/units/a1-u01', '/units/a1-u01/vocab', '/units/a1-u01/test', '/exams/a1', '/placement', '/mistakes']);

it('shows the overview of a unit', function () {
    $this->get(route('units.show', 'a1-u01'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('units/Show')
        ->where('unit.id', 'a1-u01')
        ->where('unit.order', 1)
        ->where('unit.title', $this->unit['title'])
        ->where('unit.goalRo', $this->unit['goalRo'])
        ->where('unit.grammarTitle', $this->unit['grammar']['titleRo'])
        ->where('unit.video', $this->unit['video'])
        ->where('unit.steps', [])
        ->where('unit.passed', false)
        ->where('unit.review', false)
        ->where('level', ['id' => 'a1', 'name' => 'A1', 'title' => 'Beginner', 'color' => '#22c55e'])
        ->where('bank.total', count($this->unit['bank']))
        ->where('bank.new', count($this->unit['bank']))
        ->where('rolePlay', null)
        ->where('passMark', 0.8)
        ->missing('unit.vocab'));
});

it('shows the steps done, the best test score and the role-play score', function () {
    $this->postJson(route('api.units.step', 'a1-u01'), ['step' => 'vocab']);
    $this->postJson(route('api.units.step', 'a1-u01'), ['step' => 'grammar']);
    $this->postJson(route('api.units.test', 'a1-u01'), ['score' => 0.5]);
    $this->postJson(route('api.speaking.conversation'), ['scenario' => 'role-a1-u01', 'score' => 0.75]);
    $this->postJson(route('api.words.known'), ['words' => [$this->unit['bank'][0]['en']]]);

    $this->get(route('units.show', 'a1-u01'))->assertInertia(fn (Assert $page) => $page
        ->where('unit.steps', ['vocab' => true, 'grammar' => true])
        ->where('unit.best', 0.5)
        ->where('unit.tries', 1)
        ->where('rolePlay', 0.75)
        ->where('bank.learned', 1)
        ->where('bank.new', count($this->unit['bank']) - 1));
});

it('shows a locked unit and a unit that does not exist like the original', function (string $uri, string $reason) {
    $this->get($uri)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('units/Unavailable')
        ->where('reason', $reason)
        ->missing('unit'));
})->with([
    ['/units/a1-u02', 'locked'],
    ['/units/a1-u02/test', 'locked'],
    ['/units/b1-u01', 'locked'],
    ['/units/a1-u99', 'missing'],
    ['/units/a1-u99/vocab', 'missing'],
]);

it('opens the next unit once the test of the one before is passed', function () {
    $this->postJson(route('api.units.test', 'a1-u01'), ['score' => 0.9]);

    $this->get(route('units.show', 'a1-u02'))->assertInertia(fn (Assert $page) => $page->component('units/Show'));
});

it('opens the units of the levels below the placement level', function () {
    $this->postJson(route('api.placement.store'), ['scores' => ['a1' => 0.9, 'a2' => 0.9, 'b1' => 0.2]]);

    $this->get(route('units.show', 'a2-u07'))->assertInertia(fn (Assert $page) => $page->component('units/Show'));
    $this->get(route('units.show', 'b1-u02'))->assertInertia(fn (Assert $page) => $page->component('units/Unavailable'));
});

it('does not know other steps', function () {
    $this->get('/units/a1-u01/nope')->assertNotFound();
});

it('sends each step only its own content', function (string $step, string $prop) {
    $others = array_diff(['vocab', 'grammar', 'dialogue', 'exercises', 'reading', 'test'], [$prop]);

    $this->get(route('units.step', ['unit' => 'a1-u01', 'step' => $step]))->assertOk()->assertInertia(function (Assert $page) use ($step, $prop, $others) {
        $page->component('units/Step')
            ->where('step', $step)
            ->where('unit.id', 'a1-u01')
            ->where('unit.emoji', $this->unit['emoji'])
            ->where('level.color', '#22c55e')
            ->has($prop);

        foreach ($others as $other) {
            $page->missing($other);
        }
    });
})->with([
    ['vocab', 'vocab'],
    ['grammar', 'grammar'],
    ['dialogue', 'dialogue'],
    ['practice', 'exercises'],
    ['reading', 'reading'],
    ['test', 'test'],
]);

it('sends the lesson content as the content files have it', function () {
    $this->get(route('units.step', ['unit' => 'a1-u01', 'step' => 'vocab']))
        ->assertInertia(fn (Assert $page) => $page->where('vocab', $this->unit['vocab']));
    $this->get(route('units.step', ['unit' => 'a1-u01', 'step' => 'grammar']))
        ->assertInertia(fn (Assert $page) => $page->where('grammar', $this->unit['grammar']));
    $this->get(route('units.step', ['unit' => 'a1-u01', 'step' => 'dialogue']))
        ->assertInertia(fn (Assert $page) => $page->where('dialogue', $this->unit['dialogue']));
});

it('gives the exercises and the reading questions their mistake keys', function () {
    $this->get(route('units.step', ['unit' => 'a1-u01', 'step' => 'practice']))->assertInertia(fn (Assert $page) => $page
        ->has('exercises', count($this->unit['exercises']))
        ->where('exercises.0', ['ex' => $this->unit['exercises'][0], 'unit' => 'a1-u01', 'key' => 'a1-u01:0'])
        ->where('exercises.5.key', 'a1-u01:5'));

    $this->get(route('units.step', ['unit' => 'a1-u01', 'step' => 'reading']))->assertInertia(fn (Assert $page) => $page
        ->where('reading.title', $this->unit['reading']['title'])
        ->where('reading.text', $this->unit['reading']['text'])
        ->where('reading.textRo', $this->unit['reading']['textRo'])
        ->where('reading.questions.0', ['ex' => $this->unit['reading']['questions'][0], 'unit' => 'a1-u01', 'key' => 'a1-u01:r0'])
        ->where('reading.questions.1.key', 'a1-u01:r1'));
});

it('puts the unit test together on the server', function () {
    $this->postJson(route('api.units.step', 'a1-u01'), ['step' => 'vocab']);
    $this->postJson(route('api.units.step', 'a1-u01'), ['step' => 'reading']);

    $this->get(route('units.step', ['unit' => 'a1-u01', 'step' => 'test']))->assertInertia(fn (Assert $page) => $page
        ->has('test.items', 12)
        ->where('test.items', fn ($items) => collect($items)->where('fresh', true)->count() === 6
            && collect($items)->every(fn (array $item) => preg_match($item['fresh'] ? '/^a1-u01:t\d+$/' : '/^a1-u01:\d+$/', $item['key']) === 1
                && $item['ex']['type'] !== 'match'))
        ->where('test.size', 12)
        ->where('test.hasNew', true)
        ->where('test.missing', ['grammar', 'dialogue', 'practice'])
        ->has('test.units', 10)
        ->where('test.units.1', ['id' => 'a1-u02', 'order' => 2, 'title' => app(ContentRepository::class)->unitOutline('a1-u02')['title']])
        ->where('unit.steps', ['vocab' => true, 'reading' => true]));
});
