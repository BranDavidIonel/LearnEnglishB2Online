<?php

use App\Enums\UnitStep;
use App\Services\Content\ContentRepository;
use App\Services\Progress\UnitService;
use App\Services\Progress\XpService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->units = app(UnitService::class);
    $this->xp = app(XpService::class);
    $this->user = freshLearner();
});

it('gives 10 XP the first time a step is done, nothing after', function () {
    $first = $this->units->completeStep($this->user, 'a1-u01', UnitStep::Grammar);
    $again = $this->units->completeStep($this->user, 'a1-u01', UnitStep::Grammar);

    expect($first['first'])->toBeTrue()
        ->and($first['xp'])->toBe(10)
        ->and($again['first'])->toBeFalse()
        ->and($again['xp'])->toBe(0)
        ->and($this->xp->total($this->user))->toBe(10)
        ->and($this->user->unitProgress()->sole()->steps)->toBe(['grammar']);
});

it('gives no XP for the practice step (the page adds XP for the answers)', function () {
    $result = $this->units->completeStep($this->user, 'a1-u01', UnitStep::Practice);

    expect($result)->toMatchArray(['first' => true, 'xp' => 0])
        ->and($this->user->profile()->exists())->toBeFalse();
});

it('puts the core words of the unit into the deck with the vocabulary step', function () {
    $this->units->completeStep($this->user, 'a1-u01', UnitStep::Vocab);
    $vocab = app(ContentRepository::class)->unitOutline('a1-u01')['vocab'];

    expect($this->user->wordReviews()->orderBy('id')->pluck('word')->all())->toBe($vocab)
        ->and($this->user->wordReviews()->first()->due_on->toDateString())->toBe('2026-11-21')
        ->and($this->user->wordReviews()->where('box', '>', 0)->count())->toBe(0);
});

it('keeps the words already in the deck as they are', function () {
    $vocab = app(ContentRepository::class)->unitOutline('a1-u01')['vocab'];
    $this->user->wordReviews()->create(['word' => $vocab[0], 'box' => 4, 'due_on' => '2026-12-01', 'ok' => 4]);

    $this->units->completeStep($this->user, 'a1-u01', UnitStep::Vocab);

    expect($this->user->wordReviews()->where('word', $vocab[0])->sole()->box)->toBe(4)
        ->and($this->user->wordReviews()->count())->toBe(count($vocab));
});

it('passes a unit test from 80%: 30 XP the first time, 10 after, none below', function () {
    $failed = $this->units->recordUnitTest($this->user, 'a1-u01', 0.79);
    expect($failed)->toMatchArray(['passed' => false, 'first' => false, 'xp' => 0]);

    $passed = $this->units->recordUnitTest($this->user, 'a1-u01', 0.8);
    expect($passed)->toMatchArray(['passed' => true, 'first' => true, 'xp' => 30]);

    $again = $this->units->recordUnitTest($this->user, 'a1-u01', 0.95);
    expect($again)->toMatchArray(['passed' => true, 'first' => false, 'xp' => 10]);

    $unit = $this->user->unitProgress()->sole();
    expect($unit->tries)->toBe(3)
        ->and($unit->best)->toBe(0.95)
        ->and($unit->passed)->toBeTrue()
        ->and($this->xp->total($this->user))->toBe(40);
});

it('keeps the best score and the pass after a worse test, and takes a unit out of review when passed', function () {
    passUnits($this->user, ['a1-u01'], ['review' => true, 'best' => 0.9]);

    $this->units->recordUnitTest($this->user, 'a1-u01', 0.5);
    $unit = $this->user->unitProgress()->sole();
    expect($unit->best)->toBe(0.9)->and($unit->passed)->toBeTrue()->and($unit->review)->toBeTrue();

    $result = $this->units->recordUnitTest($this->user, 'a1-u01', 0.85);
    expect($result['xp'])->toBe(10)->and($this->user->unitProgress()->sole()->review)->toBeFalse();
});
