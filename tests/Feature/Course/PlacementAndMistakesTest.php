<?php

use App\Models\User;
use App\Services\Content\ContentRepository;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->actingAs(User::factory()->create());
});

it('sends the placement questions of every level, in order', function () {
    $content = app(ContentRepository::class);

    $this->get(route('placement'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Placement')
        ->has('levels', 4)
        ->where('levels.0', ['id' => 'a1', 'name' => 'A1', 'title' => 'Beginner', 'color' => '#22c55e', 'firstUnit' => 'a1-u01'])
        ->where('levels.3.firstUnit', 'b2-u01')
        ->has('parts', 4)
        ->where('parts.0.level', 'a1')
        ->where('parts.3.level', 'b2')
        ->has('parts.0.items', 12)
        ->where('parts.1.items.0', ['ex' => $content->placement('a2')[0]])
        ->where('last', null)
        ->where('passMark', 0.75));
});

it('shows the last placement result', function () {
    $this->postJson(route('api.placement.store'), ['scores' => ['a1' => 0.9, 'a2' => 0.5]]);

    $this->get(route('placement'))->assertInertia(fn (Assert $page) => $page
        ->where('last.start', 1)
        ->where('last.level', 'a2')
        ->where('last.date', '2026-11-21'));
});

it('has nothing to review without mistakes', function () {
    $this->get(route('mistakes'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Mistakes')
        ->where('items', [])
        ->where('units', []));
});

it('lists the mistakes that can be practised, with the names of their units', function () {
    $content = app(ContentRepository::class);
    $first = $content->unit('a1-u01');
    $second = $content->unit('a1-u02');
    $this->postJson(route('api.mistakes.update'), ['add' => ['a1-u01:3', 'a1-u01:r0', 'a1-u02:t1', 'a1-u01:t0', 'a9-u01:2']]);

    $this->get(route('mistakes'))->assertInertia(fn (Assert $page) => $page
        ->where('items', [
            ['ex' => $first['exercises'][3], 'unit' => 'a1-u01', 'key' => 'a1-u01:3'],
            ['ex' => $second['testItems'][1], 'unit' => 'a1-u02', 'key' => 'a1-u02:t1'],
            ['ex' => $first['testItems'][0], 'unit' => 'a1-u01', 'key' => 'a1-u01:t0'],
        ])
        ->where('units.a1-u01', ['emoji' => $first['emoji'], 'level' => 'A1', 'order' => 1, 'grammarTitle' => $first['grammar']['titleRo']])
        ->where('units.a1-u02.order', 2)
        ->missing('units.a9-u01'));
});
