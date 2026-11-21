<?php

use App\Enums\CambridgePaper;
use App\Enums\Readiness;
use App\Services\Progress\CambridgeService;
use App\Services\Progress\CourseProgressService;
use App\Services\Progress\XpService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->cambridge = app(CambridgeService::class);
    $this->user = freshLearner();
});

it('stores the result of a paper with its day: 40 XP when marked by the app, 20 when self-assessed', function () {
    $reading = $this->cambridge->record($this->user, 'pet', 'pet-test1', CambridgePaper::Reading, ['score' => 0.8125, 'correct' => 26, 'total' => 32, 'parts' => [5, 5, 4, 3, 5, 4], 'date' => '2001-01-01']);
    $writing = $this->cambridge->record($this->user, 'pet', 'pet-test1', CambridgePaper::Writing, ['score' => 0.5]);

    expect($reading)->toBe(['result' => ['score' => 0.8125, 'correct' => 26, 'total' => 32, 'parts' => [5, 5, 4, 3, 5, 4], 'date' => '2026-11-21'], 'xp' => 40])
        ->and($writing['xp'])->toBe(20)
        ->and(app(XpService::class)->total($this->user))->toBe(60)
        ->and($this->cambridge->state($this->user, 'pet', 'pet-test1'))->toBe([
            'reading' => ['score' => 0.8125, 'correct' => 26, 'total' => 32, 'parts' => [5, 5, 4, 3, 5, 4], 'date' => '2026-11-21'],
            'writing' => ['score' => 0.5, 'date' => '2026-11-21'],
        ])
        ->and($this->cambridge->state($this->user, 'pet', 'pet-test2'))->toBe([]);
});

it('replaces the result of a paper taken again', function () {
    $this->cambridge->record($this->user, 'pet', 'pet-test1', CambridgePaper::Listening, ['score' => 0.4]);
    $this->travelTo('2026-11-22 10:00');
    $this->cambridge->record($this->user, 'pet', 'pet-test1', CambridgePaper::Listening, ['score' => 0.9]);

    expect($this->cambridge->tests($this->user, 'pet'))->toBe(['pet-test1' => ['listening' => ['score' => 0.9, 'date' => '2026-11-22']]]);
});

it('keeps the writing drafts by task', function () {
    $this->cambridge->saveDraft($this->user, 'pet', 'pet-test1', 1, 'My perfect day…');
    $this->cambridge->saveDraft($this->user, 'pet', 'pet-test1', 0, 'Hello!');
    $this->cambridge->saveDraft($this->user, 'pet', 'pet-test1', 1, 'My perfect day would start');

    expect($this->cambridge->state($this->user, 'pet', 'pet-test1'))->toBe(['drafts' => [1 => 'My perfect day would start', 0 => 'Hello!']]);
});

it('is not ready before Reading or Listening is done', function () {
    $this->cambridge->record($this->user, 'pet', 'pet-test1', CambridgePaper::Writing, ['score' => 0.9]);

    expect($this->cambridge->readiness($this->user, 'pet'))->toMatchArray(['verdict' => Readiness::Unknown, 'done' => 1, 'overall' => 0.9]);
});

it('is ready with all four papers, Reading, Listening and the average at 70%', function () {
    foreach (['reading' => 0.7, 'listening' => 0.75, 'writing' => 0.6, 'speaking' => 0.9] as $paper => $score) {
        $this->cambridge->record($this->user, 'pet', 'pet-test1', CambridgePaper::from($paper), ['score' => $score]);
    }

    $readiness = $this->cambridge->readiness($this->user, 'pet');

    expect($readiness['verdict'])->toBe(Readiness::Ready)
        ->and($readiness['done'])->toBe(4)
        ->and($readiness['overall'])->toEqualWithDelta(0.7375, 1e-12)
        ->and(array_keys($readiness['latest']))->toBe(['reading', 'listening', 'writing', 'speaking'])
        ->and($readiness['course'])->toBe(app(CourseProgressService::class)->progressTo($this->user, 'b1'));
});

it('is close from a 60% average and not yet below', function () {
    $this->cambridge->record($this->user, 'fce', 'fce-test1', CambridgePaper::Reading, ['score' => 0.65]);
    expect($this->cambridge->readiness($this->user, 'fce')['verdict'])->toBe(Readiness::Close);

    $this->cambridge->record($this->user, 'fce', 'fce-test1', CambridgePaper::Listening, ['score' => 0.5]);
    expect($this->cambridge->readiness($this->user, 'fce')['verdict'])->toBe(Readiness::NotYet);

    // A weak marked paper keeps "ready" away even with a high average.
    foreach (['listening' => 0.65, 'writing' => 1, 'speaking' => 1] as $paper => $score) {
        $this->cambridge->record($this->user, 'fce', 'fce-test1', CambridgePaper::from($paper), ['score' => $score]);
    }
    expect($this->cambridge->readiness($this->user, 'fce')['verdict'])->toBe(Readiness::Close);
});

it('takes the latest result of each paper over the tests (on the same day, the test opened last)', function () {
    $this->cambridge->record($this->user, 'pet', 'pet-test2', CambridgePaper::Reading, ['score' => 0.5]);
    $this->cambridge->record($this->user, 'pet', 'pet-test1', CambridgePaper::Reading, ['score' => 0.9]);
    expect($this->cambridge->readiness($this->user, 'pet')['latest']['reading']['score'])->toBe(0.9);

    $this->travelTo('2026-11-23 10:00');
    $this->cambridge->record($this->user, 'pet', 'pet-test1', CambridgePaper::Reading, ['score' => 0.8]);
    expect($this->cambridge->readiness($this->user, 'pet')['latest']['reading']['score'])->toBe(0.8);
});
