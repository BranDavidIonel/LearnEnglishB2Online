<?php

use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('looks up a tapped word', function () {
    $this->getJson(route('api.words.lookup', ['word' => 'went', 'sentence' => 'We went home.']))
        ->assertOk()
        ->assertJsonPath('lookup.word', 'went')
        ->assertJsonPath('lookup.found', true)
        ->assertJsonPath('lookup.base', 'go')
        ->assertJsonPath('lookup.relation', 'past')
        ->assertJsonPath('lookup.verb.v2', 'went')
        ->assertJsonMissingPath('learner');
});
