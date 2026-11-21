<?php

use App\Services\Progress\CourseProgressService;
use App\Services\Progress\PlacementService;
use App\Services\Progress\XpService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->placement = app(PlacementService::class);
    $this->user = freshLearner();
});

it('starts at the first level scored below 75%', function (array $scores, int $start, string $level) {
    $result = $this->placement->recordPlacement($this->user, $scores);

    expect($result['start'])->toBe($start)
        ->and($result['level'])->toBe($level)
        ->and(app(CourseProgressService::class)->startIndex($this->user))->toBe($start);
})->with([
    'nothing known' => [['a1' => 0.5], 0, 'a1'],
    'A1 at exactly 75%' => [['a1' => 0.75, 'a2' => 0.74], 1, 'a2'],
    'A1 and A2 known' => [['a1' => 0.83, 'a2' => 0.75, 'b1' => 0.58], 2, 'b1'],
    'a gap stops the count' => [['a1' => 0.5, 'a2' => 1, 'b1' => 1], 0, 'a1'],
    'never past the last level' => [['a1' => 1, 'a2' => 1, 'b1' => 1, 'b2' => 1], 3, 'b2'],
]);

it('keeps the scores and the day, gives 20 XP each time and replaces the old result', function () {
    $this->placement->recordPlacement($this->user, ['a1' => 1, 'a2' => 0.5]);
    $this->placement->recordPlacement($this->user, ['a1' => 0.25]);

    expect($this->placement->placement($this->user))->toBe(['start' => 0, 'level' => 'a1', 'scores' => ['a1' => 0.25], 'date' => '2026-11-21'])
        ->and($this->user->placement()->count())->toBe(1)
        ->and(app(XpService::class)->total($this->user))->toBe(40);
});

it('has no placement before the test', function () {
    expect($this->placement->placement($this->user))->toBeNull();
});
