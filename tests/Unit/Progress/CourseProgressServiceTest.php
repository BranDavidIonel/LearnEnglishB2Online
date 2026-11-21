<?php

use App\Enums\NextActionKind;
use App\Services\Progress\CourseProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->course = app(CourseProgressService::class);
    $this->user = freshLearner();
});

it('opens only A1 and its first unit to a new learner', function () {
    expect($this->course->levelUnlocked($this->user, 'a1'))->toBeTrue()
        ->and($this->course->levelUnlocked($this->user, 'a2'))->toBeFalse()
        ->and($this->course->unitUnlocked($this->user, 'a1-u01'))->toBeTrue()
        ->and($this->course->unitUnlocked($this->user, 'a1-u02'))->toBeFalse()
        ->and($this->course->unitUnlocked($this->user, 'a2-u01'))->toBeFalse()
        ->and($this->course->unitUnlocked($this->user, 'x1-u01'))->toBeFalse()
        ->and($this->course->startIndex($this->user))->toBe(0)
        ->and($this->course->currentLevel($this->user)['id'])->toBe('a1')
        ->and($this->course->achieved($this->user))->toBeNull();
});

it('opens the next unit when the one before it is passed', function () {
    passUnits($this->user, ['a1-u01']);

    expect($this->course->unitUnlocked($this->user, 'a1-u02'))->toBeTrue()
        ->and($this->course->unitUnlocked($this->user, 'a1-u03'))->toBeFalse();
});

it('keeps a unit in review open', function () {
    passUnits($this->user, ['a1-u05'], ['passed' => false, 'review' => true]);

    expect($this->course->unitUnlocked($this->user, 'a1-u05'))->toBeTrue()
        ->and($this->course->reviewUnits($this->user, 'a1'))->toBe(['a1-u05']);
});

it('opens a level when the exam before it is passed, and all its units once done', function () {
    passExam($this->user, 'a1');

    expect($this->course->levelPassed($this->user, 'a1'))->toBeTrue()
        ->and($this->course->levelDone($this->user, 'a1'))->toBeTrue()
        ->and($this->course->unitUnlocked($this->user, 'a1-u10'))->toBeTrue()
        ->and($this->course->levelUnlocked($this->user, 'a2'))->toBeTrue()
        ->and($this->course->unitUnlocked($this->user, 'a2-u01'))->toBeTrue()
        ->and($this->course->levelUnlocked($this->user, 'b1'))->toBeFalse()
        ->and($this->course->currentLevel($this->user)['id'])->toBe('a2')
        ->and($this->course->achieved($this->user))->toBe('a1');
});

it('counts the levels below the placement level as known until their exam is passed', function () {
    placeAt($this->user, 2);

    expect($this->course->levelSkipped($this->user, 'a1'))->toBeTrue()
        ->and($this->course->levelSkipped($this->user, 'a2'))->toBeTrue()
        ->and($this->course->levelSkipped($this->user, 'b1'))->toBeFalse()
        ->and($this->course->levelUnlocked($this->user, 'b1'))->toBeTrue()
        ->and($this->course->levelUnlocked($this->user, 'b2'))->toBeFalse()
        ->and($this->course->unitUnlocked($this->user, 'a2-u07'))->toBeTrue()
        ->and($this->course->currentLevel($this->user)['id'])->toBe('b1')
        ->and($this->course->achieved($this->user))->toBe('a2')
        ->and($this->course->levelProgress($this->user, 'a1'))->toBe(1.0);

    passExam($this->user, 'a1');
    expect($this->course->levelSkipped($this->user, 'a1'))->toBeFalse()
        ->and($this->course->levelDone($this->user, 'a1'))->toBeTrue();
});

it('works out the progress of units, levels and the road to a level', function () {
    passUnits($this->user, ['a1-u01']);
    passUnits($this->user, ['a1-u02'], ['steps' => ['vocab', 'grammar'], 'passed' => false, 'tries' => 0]);
    passUnits($this->user, ['a1-u03'], ['review' => true]);

    expect($this->course->unitProgress($this->user, 'a1-u01'))->toBe(1.0)
        ->and($this->course->unitProgress($this->user, 'a1-u02'))->toEqualWithDelta(0.32, 1e-12)
        // passed but sent back to review: only its steps count
        ->and($this->course->unitProgress($this->user, 'a1-u03'))->toEqualWithDelta(0.8, 1e-12)
        ->and($this->course->unitProgress($this->user, 'a1-u04'))->toBe(0.0)
        ->and($this->course->levelProgress($this->user, 'a1'))->toEqualWithDelta(0.9 * (1 + 0.32 + 0.8) / 10, 1e-12)
        ->and($this->course->levelProgress($this->user, 'a2'))->toBe(0.0)
        ->and($this->course->progressTo($this->user, 'a2'))->toEqualWithDelta(0.9 * 2.12 / 10 / 2, 1e-12)
        ->and($this->course->progressTo($this->user, 'c1'))->toBe(0.0);
});

it('ignores unknown steps in the unit state', function () {
    passUnits($this->user, ['a1-u01'], ['steps' => ['reading', 'vocab', 'test', 'other'], 'passed' => false]);

    expect($this->course->unitState($this->user, 'a1-u01')['steps'])->toBe(['vocab', 'reading'])
        ->and($this->course->unitState($this->user, 'a1-u09'))->toBe(['steps' => [], 'best' => 0.0, 'passed' => false, 'review' => false, 'tries' => 0]);
});

it('says what to do next', function () {
    expect($this->course->nextAction($this->user))
        ->toBe(['kind' => NextActionKind::Unit, 'level' => 'a1', 'unit' => 'a1-u01', 'step' => 'vocab', 'review' => false]);

    passUnits($this->user, ['a1-u01']);
    passUnits($this->user, ['a1-u02'], ['steps' => ['vocab', 'dialogue'], 'passed' => false]);
    expect($this->course->nextAction($this->user))->toMatchArray(['unit' => 'a1-u02', 'step' => 'grammar']);

    passUnits($this->user, ['a1-u02'], ['passed' => false]);
    expect($this->course->nextAction($this->user))->toMatchArray(['unit' => 'a1-u02', 'step' => 'test']);

    passUnits($this->user, unitsOf('a1'));
    expect($this->course->nextAction($this->user))->toMatchArray(['kind' => NextActionKind::Exam, 'level' => 'a1', 'unit' => null]);

    passUnits($this->user, ['a1-u04', 'a1-u02'], ['review' => true]);
    expect($this->course->nextAction($this->user))->toMatchArray(['kind' => NextActionKind::Unit, 'unit' => 'a1-u02', 'step' => 'test', 'review' => true]);
});

it('is done when every level is done', function () {
    placeAt($this->user, 3);
    passExam($this->user, 'b2');

    expect($this->course->currentLevel($this->user)['id'])->toBe('b2')
        ->and($this->course->achieved($this->user))->toBe('b2')
        ->and($this->course->nextAction($this->user))->toMatchArray(['kind' => NextActionKind::Done, 'level' => 'b2'])
        ->and($this->course->progressTo($this->user, 'b2'))->toBe(1.0);
});

it('gives the stored exam state', function () {
    expect($this->course->examState($this->user, 'a1'))->toBeNull();

    passExam($this->user, 'a1');

    expect($this->course->examState($this->user, 'a1'))->toMatchArray(['best' => 0.9, 'passed' => true, 'tries' => 1]);
});
