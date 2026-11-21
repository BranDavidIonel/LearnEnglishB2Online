<?php

use App\Services\Content\ContentRepository;
use App\Services\Progress\MistakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mistakes = app(MistakeService::class);
    $this->user = freshLearner();
});

it('adds a question once and clears it', function () {
    expect($this->mistakes->add($this->user, 'a1-u01:3'))->toBeTrue()
        ->and($this->mistakes->add($this->user, 'a1-u01:3'))->toBeFalse()
        ->and($this->mistakes->add($this->user, ''))->toBeFalse()
        ->and($this->mistakes->add($this->user, 'a1-u01:t2'))->toBeTrue()
        ->and($this->mistakes->keys($this->user))->toBe(['a1-u01:3', 'a1-u01:t2'])
        ->and($this->mistakes->clear($this->user, 'a1-u01:3'))->toBeTrue()
        ->and($this->mistakes->clear($this->user, 'a1-u01:3'))->toBeFalse()
        ->and($this->mistakes->keys($this->user))->toBe(['a1-u01:t2']);

    freshState();
    expect($this->mistakes->keys($this->user))->toBe(['a1-u01:t2']);
});

it('keeps the latest 200 questions', function () {
    foreach (range(0, 200) as $n) {
        $this->mistakes->add($this->user, "a1-u01:{$n}");
    }

    $keys = $this->mistakes->keys($this->user);

    expect($keys)->toHaveCount(200)
        ->and($keys[0])->toBe('a1-u01:1')
        ->and($keys[199])->toBe('a1-u01:200')
        ->and($this->user->mistakes()->count())->toBe(200);
});

it('finds the questions behind the keys like the original', function () {
    $unit = app(ContentRepository::class)->unit('a1-u02');

    foreach (['a1-u02:4', 'a1-u02:t1', 'a1-u02:r0', 'a1-u02:999', 'z9-u99:1', 'a1-u02:x', 'a1-u02:', 'nonsense'] as $key) {
        $this->mistakes->add($this->user, $key);
    }

    expect($this->mistakes->items($this->user))->toBe([
        ['ex' => $unit['exercises'][4], 'unit' => 'a1-u02', 'key' => 'a1-u02:4'],
        ['ex' => $unit['testItems'][1], 'unit' => 'a1-u02', 'key' => 'a1-u02:t1'],
        // An empty number is exercise 0, as in the original.
        ['ex' => $unit['exercises'][0], 'unit' => 'a1-u02', 'key' => 'a1-u02:'],
    ])->and($this->mistakes->count($this->user))->toBe(3);
});
