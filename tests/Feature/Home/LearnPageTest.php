<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create();
});

it('sends guests to the login page', function () {
    $this->get(route('learn'))->assertRedirect(route('login'));
});

it('opens the first level and its first unit to a new learner', function () {
    $this->actingAs($this->user)->get(route('learn'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Learn')
        ->where('batch', 20)
        ->where('passMark', 0.8)
        ->has('levels', 4)
        ->where('levels.0.level.id', 'a1')
        ->where('levels.0.level.unlocked', true)
        ->where('levels.0.level.passed', false)
        ->where('levels.0.level.exam', null)
        ->where('levels.0.canLearnWords', true)
        ->where('levels.0.review', 0)
        ->where('levels.0.words.learned', 0)
        ->has('levels.0.units', 10)
        ->where('levels.0.units.0.id', 'a1-u01')
        ->where('levels.0.units.0.unlocked', true)
        ->where('levels.0.units.0.grammar', "The verb 'to be': am, is, are")
        ->where('levels.0.units.1.unlocked', false)
        ->where('levels.1.level.unlocked', false)
        ->where('levels.1.canLearnWords', false));
});

it('shows passed units, units in review and the exam result', function () {
    $this->user->unitProgress()->create(['unit' => 'a1-u01', 'steps' => ['vocab', 'grammar', 'dialogue', 'practice', 'reading'], 'passed' => true, 'best' => 0.9, 'tries' => 1]);
    $this->user->unitProgress()->create(['unit' => 'a1-u02', 'passed' => true, 'review' => true]);
    $this->user->examResults()->create(['level' => 'a1', 'best' => 0.55, 'passed' => false, 'tries' => 1]);
    $this->user->wordReviews()->create(['word' => 'hello', 'box' => 3, 'due_on' => '2026-11-28']);

    $this->actingAs($this->user)->get(route('learn'))->assertInertia(fn (Assert $page) => $page
        ->where('levels.0.units.0.passed', true)
        ->where('levels.0.units.0.progress', 1)
        ->where('levels.0.units.1.review', true)
        ->where('levels.0.units.2.unlocked', true)
        ->where('levels.0.units.3.unlocked', false)
        ->where('levels.0.review', 1)
        ->where('levels.0.level.exam.best', 0.55)
        ->where('levels.0.words.learned', 1));
});

it('credits the levels below the placement level', function () {
    $this->user->placement()->create(['start_index' => 2, 'scores' => ['a1' => 0.9, 'a2' => 0.8, 'b1' => 0.3], 'taken_on' => '2026-11-01']);

    $this->actingAs($this->user)->get(route('learn'))->assertInertia(fn (Assert $page) => $page
        ->where('levels.0.level.skipped', true)
        ->where('levels.0.level.progress', 1)
        ->where('levels.0.units.9.unlocked', true)
        ->where('levels.2.level.unlocked', true)
        ->where('levels.2.level.skipped', false)
        ->where('levels.3.level.unlocked', false));
});
