<?php

use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/** The keys every answer of the progress API carries. */
const LEARNER_KEYS = ['xp', 'xpToday', 'goal', 'streak', 'level', 'due'];

it('refuses guests on every route of the progress API', function (string $method, string $uri) {
    auth()->logout();

    $this->json($method, $uri, [])->assertUnauthorized();
})->with([
    ['POST', '/api/units/a1-u01/steps'],
    ['POST', '/api/units/a1-u01/test'],
    ['POST', '/api/exams/a1'],
    ['POST', '/api/placement'],
    ['POST', '/api/words/grade'],
    ['POST', '/api/words/known'],
    ['POST', '/api/words/sorted'],
    ['POST', '/api/words/learn'],
    ['POST', '/api/words/forget'],
    ['POST', '/api/verbs/grade'],
    ['POST', '/api/verbs/known'],
    ['POST', '/api/verbs/learn'],
    ['POST', '/api/verbs/forget'],
    ['POST', '/api/xp'],
    ['POST', '/api/mistakes'],
    ['POST', '/api/speaking/spoken'],
    ['POST', '/api/speaking/conversations'],
    ['POST', '/api/speaking/records/pairs'],
    ['POST', '/api/games/memory/record'],
    ['PUT', '/api/cambridge/pet/pet-test1/drafts'],
    ['POST', '/api/cambridge/pet/pet-test1/reading'],
    ['PATCH', '/api/settings'],
    ['PUT', '/api/goal'],
    ['PUT', '/api/flags'],
    ['GET', '/api/progress/export'],
    ['POST', '/api/progress/import'],
    ['DELETE', '/api/progress'],
]);

it('completes a lesson step', function () {
    $this->postJson(route('api.units.step', 'a1-u01'), ['step' => 'vocab'])
        ->assertOk()
        ->assertJsonPath('first', true)
        ->assertJsonPath('xp', 10)
        ->assertJsonPath('unit.id', 'a1-u01')
        ->assertJsonPath('unit.steps', ['vocab'])
        ->assertJsonPath('unit.progress', fn (float $progress) => abs($progress - 0.16) < 1e-9)
        ->assertJsonPath('unit.unlocked', true)
        ->assertJsonPath('learner.xp', 10)
        ->assertJsonPath('learner.xpToday', 10)
        ->assertJsonPath('learner.streak', 1)
        ->assertJsonPath('learner.due.words', 16)
        ->assertJsonStructure(['learner' => LEARNER_KEYS, 'unit' => ['id', 'order', 'title', 'titleRo', 'emoji', 'levelId', 'index', 'unlocked', 'progress', 'passed', 'review', 'best', 'tries', 'steps']]);

    $this->postJson(route('api.units.step', 'a1-u01'), ['step' => 'vocab'])->assertJsonPath('first', false)->assertJsonPath('xp', 0);

    // Today's point of the progress chart follows every change.
    expect($this->user->snapshots()->sole()->xp)->toBe(10);
});

it('validates the step and the unit', function () {
    $this->postJson(route('api.units.step', 'a1-u01'), ['step' => 'test'])->assertUnprocessable()->assertJsonValidationErrors('step');
    $this->postJson(route('api.units.step', 'a1-u01'), [])->assertJsonValidationErrors('step');
    $this->postJson('/api/units/a9-u99/steps', ['step' => 'vocab'])->assertNotFound();
});

it('records a unit test and opens the next unit', function () {
    $this->postJson(route('api.units.test', 'a1-u01'), ['score' => 0.5])
        ->assertOk()
        ->assertJsonPath('passed', false)
        ->assertJsonPath('next.unlocked', false);

    $this->postJson(route('api.units.test', 'a1-u01'), ['score' => 0.9])
        ->assertJsonPath('passed', true)
        ->assertJsonPath('first', true)
        ->assertJsonPath('xp', 30)
        ->assertJsonPath('unit.passed', true)
        ->assertJsonPath('unit.progress', 1)
        ->assertJsonPath('unit.tries', 2)
        ->assertJsonPath('next.id', 'a1-u02')
        ->assertJsonPath('next.unlocked', true)
        ->assertJsonPath('review', [])
        ->assertJsonPath('learner.xp', 30);

    $this->postJson(route('api.units.test', 'a1-u01'), ['score' => 1.2])->assertJsonValidationErrors('score');
    $this->postJson(route('api.units.test', 'b2-u10'), ['score' => 1])->assertJsonPath('next', null);
});

it('records a level exam', function () {
    $results = [];
    foreach (range(1, 10) as $n) {
        foreach (range(1, 3) as $i) {
            $results[] = ['unit' => sprintf('a1-u%02d', $n), 'correct' => $n > 2];
        }
    }

    $this->postJson(route('api.exams.store', 'a1'), ['results' => $results])
        ->assertOk()
        ->assertJsonPath('passed', true)
        ->assertJsonPath('xp', 150)
        ->assertJsonPath('weak', ['a1-u01', 'a1-u02'])
        ->assertJsonPath('byUnit.a1-u01', ['ok' => 0, 'n' => 3])
        ->assertJsonPath('level.passed', true)
        ->assertJsonPath('level.exam.tries', 1)
        ->assertJsonPath('level.title', 'Beginner')
        ->assertJsonPath('next.id', 'a2')
        ->assertJsonPath('next.unlocked', true)
        ->assertJsonCount(10, 'units')
        ->assertJsonPath('learner.level.id', 'a2');
});

it('validates the exam answers', function () {
    $this->postJson(route('api.exams.store', 'a1'), ['results' => []])->assertJsonValidationErrors('results');
    $this->postJson(route('api.exams.store', 'a1'), ['results' => [['unit' => 'a2-u01', 'correct' => true]]])->assertJsonValidationErrors('results.0.unit');
    $this->postJson(route('api.exams.store', 'a1'), ['results' => [['unit' => 'a1-u01']]])->assertJsonValidationErrors('results.0.correct');
    $this->postJson('/api/exams/c1', ['results' => []])->assertNotFound();
});

it('records the placement test', function () {
    $this->postJson(route('api.placement.store'), ['scores' => ['a1' => 0.9, 'a2' => 0.8, 'b1' => 0.5]])
        ->assertOk()
        ->assertJsonPath('start', 2)
        ->assertJsonPath('level', 'b1')
        ->assertJsonPath('xp', 20)
        ->assertJsonPath('placement.scores.a2', 0.8)
        ->assertJsonPath('placement.date', '2026-11-21')
        ->assertJsonPath('levels.0.skipped', true)
        ->assertJsonPath('levels.2.unlocked', true)
        ->assertJsonPath('levels.3.unlocked', false)
        ->assertJsonPath('learner.level.id', 'b1');

    $this->postJson(route('api.placement.store'), ['scores' => ['c1' => 1]])->assertJsonValidationErrors('scores');
    $this->postJson(route('api.placement.store'), ['scores' => ['a1' => 2]])->assertJsonValidationErrors('scores.a1');
});
