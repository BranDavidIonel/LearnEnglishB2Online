<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create();
});

it('sends guests to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

it('welcomes a learner who has done nothing yet', function () {
    $this->actingAs($this->user)->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Home')
        ->where('next.kind', 'welcome')
        ->where('next.unit.id', 'a1-u01')
        ->where('next.level.id', 'a1')
        ->where('passMark', 0.8)
        ->where('counts.placement', false)
        ->where('counts.verbs.learned', 0)
        ->where('wordsToLearn.level', ['id' => 'a1', 'name' => 'A1', 'color' => '#22c55e'])
        ->where('wordsToLearn.batch', 20)
        ->where('road.achieved', null)
        ->where('road.current', 'A1')
        ->has('road.levels', 4)
        ->where('road.levels.0.unlocked', true)
        ->where('road.levels.1.unlocked', false)
        ->where('road.levels.0.title', 'Beginner')
        ->has('road.goals', 2)
        ->where('road.goals.0.level.id', 'b1')
        ->where('road.goals.1.level.id', 'b2')
        ->has('week', 7)
        ->where('week.0', ['date' => '2026-11-15', 'xp' => 0])
        ->where('week.6.date', '2026-11-21'));
});

it('sends the learner to the next lesson step', function () {
    $this->user->profile()->create(['xp' => 40]);
    $this->user->unitProgress()->create(['unit' => 'a1-u01', 'steps' => ['vocab', 'grammar'], 'passed' => false]);
    $this->user->dailyXp()->create(['day' => '2026-11-20', 'xp' => 25]);

    $this->actingAs($this->user)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('next.kind', 'unit')
        ->where('next.unit.id', 'a1-u01')
        ->where('next.unit.order', 1)
        ->where('next.unit.title', "Hello! I'm a developer")
        ->where('next.unit.emoji', '👋')
        ->where('next.step', 'dialogue')
        ->where('next.review', false)
        ->where('week.5', ['date' => '2026-11-20', 'xp' => 25]));
});

it('sends a unit in review to its test first', function () {
    $this->user->profile()->create(['xp' => 400]);
    $this->user->unitProgress()->create(['unit' => 'a1-u01', 'passed' => true]);
    $this->user->unitProgress()->create(['unit' => 'a1-u02', 'passed' => true, 'review' => true]);

    $this->actingAs($this->user)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('next.kind', 'unit')
        ->where('next.unit.id', 'a1-u02')
        ->where('next.step', 'test')
        ->where('next.review', true));
});

it('offers the level exam once every unit is passed', function () {
    $this->user->profile()->create(['xp' => 400]);

    foreach (range(1, 10) as $n) {
        $this->user->unitProgress()->create(['unit' => sprintf('a1-u%02d', $n), 'passed' => true]);
    }

    $this->actingAs($this->user)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('next.kind', 'exam')
        ->where('next.level.id', 'a1')
        ->where('next.step', null));
});

it('starts at the placement level and counts the levels before it as reached', function () {
    $this->user->profile()->create(['xp' => 20]);
    $this->user->placement()->create(['start_index' => 2, 'scores' => ['a1' => 0.9, 'a2' => 0.8, 'b1' => 0.3], 'taken_on' => '2026-11-01']);

    $this->actingAs($this->user)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('next.kind', 'unit')
        ->where('next.unit.id', 'b1-u01')
        ->where('next.step', 'vocab')
        ->where('counts.placement', true)
        ->where('road.achieved', 'A2')
        ->where('road.current', 'B1')
        ->where('road.levels.0.done', true)
        ->where('road.levels.2.unlocked', true)
        ->where('road.levels.3.unlocked', false)
        ->where('wordsToLearn.level.id', 'a1')
        ->where('wordsToLearn.current.id', 'b1'));
});

it('says the course is done when every level is passed', function () {
    $this->user->profile()->create(['xp' => 5000]);

    foreach (['a1', 'a2', 'b1', 'b2'] as $level) {
        $this->user->examResults()->create(['level' => $level, 'best' => 0.9, 'passed' => true, 'tries' => 1]);
    }

    $this->actingAs($this->user)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('next.kind', 'done')
        ->where('road.achieved', 'B2'));
});
