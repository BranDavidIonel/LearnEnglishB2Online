<?php

use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-11-15 18:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('changes some of the settings', function () {
    $this->patchJson(route('api.settings.update'), ['theme' => 'sepia', 'rate' => 1.05])
        ->assertOk()
        ->assertExactJson([
            'settings' => ['theme' => 'sepia', 'rate' => 1.05, 'voice' => null, 'sound' => true],
            'learner' => ['xp' => 0, 'xpToday' => 0, 'goal' => 50, 'streak' => 0, 'level' => ['id' => 'a1', 'name' => 'A1', 'color' => '#22c55e'], 'due' => ['words' => 0, 'verbs' => 0, 'mistakes' => 0]],
        ]);

    $this->patchJson(route('api.settings.update'), ['sound' => false, 'voice' => 'Google UK English Female'])
        ->assertJsonPath('settings', ['theme' => 'sepia', 'rate' => 1.05, 'voice' => 'Google UK English Female', 'sound' => false]);

    $this->patchJson(route('api.settings.update'), ['theme' => 'pink'])->assertJsonValidationErrors('theme');
    $this->patchJson(route('api.settings.update'), ['rate' => 3])->assertJsonValidationErrors('rate');
});

it('sets the daily goal and the flags', function () {
    $this->putJson(route('api.goal.update'), ['goal' => 100])->assertOk()->assertJsonPath('goal', 100)->assertJsonPath('learner.goal', 100);
    $this->putJson(route('api.goal.update'), ['goal' => 75])->assertJsonValidationErrors('goal');

    $this->putJson(route('api.flags.update'), ['flag' => 'keepSkippedWords', 'value' => true])->assertOk()->assertJsonPath('flags', ['keepSkippedWords' => true]);
    $this->putJson(route('api.flags.update'), ['flag' => 'somethingElse', 'value' => true])->assertJsonValidationErrors('flag');
});

it('downloads the progress as a progress.json of the original app', function () {
    $this->postJson(route('api.xp.store'), ['amount' => 10]);

    $response = $this->get(route('api.progress.export'))->assertOk()->assertDownload('english-levels-progress.json');
    $data = json_decode($response->streamedContent(), true);

    expect($data)->toMatchArray(['v' => 1, 'xp' => 10, 'goal' => 50, 'streak' => ['count' => 1, 'last' => '2026-11-15'], 'days' => ['2026-11-15' => 10]]);
});

it('imports a progress file and replaces the progress', function () {
    $this->postJson(route('api.mistakes.update'), ['add' => ['b2-u01:1']]);

    $this->postJson(route('api.progress.import'), ['content' => file_get_contents(base_path('tests/Fixtures/progress.json'))])
        ->assertOk()
        ->assertJsonPath('settings.theme', 'light')
        ->assertJsonPath('learner.xp', 230)
        ->assertJsonPath('learner.streak', 3);

    expect($this->user->mistakes()->where('key', 'b2-u01:1')->exists())->toBeFalse();

    $this->postJson(route('api.progress.import'), ['content' => 'not json'])->assertJsonValidationErrors('content');
    $this->postJson(route('api.progress.import'), ['content' => '{"words": {"hello": {}}}'])->assertJsonValidationErrors('words.hello.box');
    $this->postJson(route('api.progress.import'), [])->assertJsonValidationErrors('content');
});

it('resets all progress', function () {
    $this->patchJson(route('api.settings.update'), ['theme' => 'light']);
    $this->postJson(route('api.xp.store'), ['amount' => 10]);

    $this->deleteJson(route('api.progress.destroy'))
        ->assertOk()
        ->assertJsonPath('settings.theme', 'dark')
        ->assertJsonPath('learner.xp', 0);

    expect($this->user->dailyXp()->count())->toBe(0);
});
