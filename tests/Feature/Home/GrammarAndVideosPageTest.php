<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('sends guests to the login page', function (string $uri) {
    $this->get($uri)->assertRedirect(route('login'));
})->with(['/grammar', '/grammar/a1-u01', '/videos']);

it('lists the grammar of every unit and marks the lessons studied', function () {
    $this->user->unitProgress()->create(['unit' => 'a1-u02', 'steps' => ['vocab', 'grammar']]);

    $this->actingAs($this->user)->get(route('grammar'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('grammar/Index')
        ->has('levels', 4)
        ->where('levels.0.level', 'a1')
        ->has('levels.0.units', 10)
        ->where('levels.0.units.0.id', 'a1-u01')
        ->where('levels.0.units.0.order', 1)
        ->where('levels.0.units.0.title', "The verb 'to be': am, is, are")
        ->where('levels.0.units.0.done', false)
        ->has('levels.0.units.0.titleRo')
        ->where('levels.0.units.1.done', true)
        ->where('levels.2.units.3.title', 'Past perfect and narrative tenses'));
});

it('shows one unit\'s grammar lesson', function () {
    $this->actingAs($this->user)->get(route('grammar', 'b1-u04'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('grammar/Show')
        ->where('unit.id', 'b1-u04')
        ->where('unit.order', 4)
        ->where('unit.grammar.title', 'Past perfect and narrative tenses')
        ->has('unit.grammar.sections')
        ->has('unit.grammar.tipsRo'));
});

it('goes back to the grammar list for a unit that does not exist', function () {
    $this->actingAs($this->user)->get('/grammar/a1-u99')->assertRedirect(route('grammar'));
    $this->actingAs($this->user)->get('/grammar/hello')->assertNotFound();
});

it('lists the videos of the units by level', function () {
    $this->actingAs($this->user)->get(route('videos'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Videos')
        ->has('levels', 4)
        ->where('levels.0.level', 'a1')
        ->has('levels.0.units', 10)
        ->where('levels.0.units.0.id', 'a1-u01')
        ->where('levels.0.units.0.level', 'A1')
        ->where('levels.0.units.0.grammar', "The verb 'to be': am, is, are")
        ->where('levels.0.units.0.video.youtubeId', 'LH57BAO9K88'));
});
