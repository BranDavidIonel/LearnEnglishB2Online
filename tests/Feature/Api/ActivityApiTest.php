<?php

use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('adds the XP of an activity, within limits', function () {
    $this->postJson(route('api.xp.store'), ['amount' => 12])->assertOk()->assertJsonPath('xp', 12)->assertJsonPath('learner.xp', 12);
    $this->postJson(route('api.xp.store'), ['amount' => 0])->assertOk()->assertJsonPath('learner.xp', 12);

    $this->postJson(route('api.xp.store'), ['amount' => 101])->assertJsonValidationErrors('amount');
    $this->postJson(route('api.xp.store'), ['amount' => -5])->assertJsonValidationErrors('amount');
    $this->postJson(route('api.xp.store'), ['amount' => 'ten'])->assertJsonValidationErrors('amount');
});

it('adds and clears mistakes', function () {
    $this->postJson(route('api.mistakes.update'), ['add' => ['a1-u01:3', 'a1-u01:t1', 'a1-u01:r0']])
        ->assertOk()
        ->assertJsonPath('added', ['a1-u01:3', 'a1-u01:t1', 'a1-u01:r0'])
        ->assertJsonPath('mistakes', 2)
        ->assertJsonPath('learner.due.mistakes', 2);

    $this->postJson(route('api.mistakes.update'), ['add' => ['a1-u01:3'], 'clear' => ['a1-u01:t1', 'a1-u09:1']])
        ->assertJsonPath('added', [])
        ->assertJsonPath('cleared', ['a1-u01:t1'])
        ->assertJsonPath('mistakes', 1);

    $this->postJson(route('api.mistakes.update'), [])->assertJsonValidationErrors(['add', 'clear']);
    $this->postJson(route('api.mistakes.update'), ['add' => ['hello']])->assertJsonValidationErrors('add.0');
});

it('counts sentences said', function () {
    $this->postJson(route('api.speaking.spoken'))->assertOk()->assertJsonPath('xp', 2)->assertJsonPath('said', 1);
    $this->postJson(route('api.speaking.spoken'), ['count' => 2, 'xp' => 3])->assertJsonPath('xp', 6)->assertJsonPath('said', 3)->assertJsonPath('learner.xp', 8);

    $this->postJson(route('api.speaking.spoken'), ['xp' => 10])->assertJsonValidationErrors('xp');
});

it('records a conversation and a role-play', function () {
    $this->postJson(route('api.speaking.conversation'), ['scenario' => 'a1-s01', 'score' => 0.8])
        ->assertOk()
        ->assertJsonPath('first', true)
        ->assertJsonPath('xp', 15)
        ->assertJsonPath('best', 0.8);

    $this->postJson(route('api.speaking.conversation'), ['scenario' => 'role-b2-u10', 'score' => 1])->assertJsonPath('first', true);
    $this->postJson(route('api.speaking.conversation'), ['scenario' => 'a1-s01', 'score' => 0.5])->assertJsonPath('xp', 5)->assertJsonPath('best', 0.8);

    $this->postJson(route('api.speaking.conversation'), ['scenario' => 'z9-s99', 'score' => 1])->assertJsonValidationErrors('scenario');
    $this->postJson(route('api.speaking.conversation'), ['scenario' => 'a1-s01', 'score' => 2])->assertJsonValidationErrors('score');
});

it('keeps the records of the speaking games and of the word games', function () {
    $this->postJson(route('api.speaking.record', 'sprint'), ['value' => 7])->assertOk()->assertJsonPath('record', true)->assertJsonPath('best.sprint', 7);
    $this->postJson(route('api.speaking.record', 'sprint'), ['value' => 5])->assertJsonPath('record', false);

    $this->postJson(route('api.games.record', 'memory'), ['value' => 30])->assertJsonPath('record', true);
    $this->postJson(route('api.games.record', 'memory'), ['value' => 24])->assertJsonPath('record', true)->assertJsonPath('records.memory', 24);
    $this->postJson(route('api.games.record', 'memory'), ['value' => 28])->assertJsonPath('record', false);

    $this->postJson(route('api.games.record', 'memory'), ['value' => -1])->assertJsonValidationErrors('value');
    $this->postJson('/api/games/chess/record', ['value' => 1])->assertNotFound();
});

it('records a Cambridge paper and keeps the drafts', function () {
    $this->postJson(route('api.cambridge.store', ['pet', 'pet-test1', 'reading']), ['score' => 0.8125, 'correct' => 26, 'total' => 32, 'parts' => [5, 5, 4, 3, 5, 4]])
        ->assertOk()
        ->assertJsonPath('xp', 40)
        ->assertJsonPath('result.date', '2026-11-21')
        ->assertJsonPath('result.parts', [5, 5, 4, 3, 5, 4])
        ->assertJsonPath('readiness.verdict', 'close')
        ->assertJsonPath('readiness.done', 1)
        ->assertJsonPath('learner.xp', 40);

    $this->putJson(route('api.cambridge.draft', ['pet', 'pet-test1']), ['task' => 0, 'text' => 'Hello Sam!'])->assertOk()->assertJsonPath('saved', true);
    $this->putJson(route('api.cambridge.draft', ['pet', 'pet-test1']), ['task' => 1, 'text' => null])->assertOk();

    expect($this->user->cambridgeTests()->sole()->drafts)->toBe([0 => 'Hello Sam!', 1 => '']);

    $this->postJson(route('api.cambridge.store', ['pet', 'pet-test1', 'writing']), ['score' => 1.5])->assertJsonValidationErrors('score');
    $this->postJson(route('api.cambridge.store', ['pet', 'pet-test9', 'writing']), ['score' => 0.5])->assertNotFound();
    $this->postJson('/api/cambridge/ielts/test1/reading', ['score' => 0.5])->assertNotFound();
});
