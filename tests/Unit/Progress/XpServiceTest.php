<?php

use App\Models\DailyXp;
use App\Services\Progress\Calendar;
use App\Services\Progress\LearnerState;
use App\Services\Progress\XpService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->xp = app(XpService::class);
    $this->user = freshLearner();
});

it('adds XP to the total and to today', function () {
    $this->xp->award($this->user, 10);
    $this->xp->award($this->user, 5);

    expect($this->xp->total($this->user))->toBe(15)
        ->and($this->xp->today($this->user))->toBe(15)
        ->and($this->xp->days($this->user))->toBe(['2026-11-21' => 15])
        ->and(DailyXp::query()->count())->toBe(1);
});

it('does nothing at all for zero XP', function () {
    $this->xp->award($this->user, 0);

    expect($this->user->profile()->exists())->toBeFalse()
        ->and($this->xp->streak($this->user))->toBe(0);
});

it('starts a streak at 1 and continues it on the next day', function () {
    $this->xp->award($this->user, 10);
    expect($this->xp->streak($this->user))->toBe(1);

    $this->travelTo('2026-11-22 23:30');
    $this->xp->award($this->user, 10);
    $this->xp->award($this->user, 10);

    expect($this->xp->streak($this->user))->toBe(2)
        ->and($this->xp->today($this->user))->toBe(20)
        ->and($this->xp->days($this->user))->toBe(['2026-11-21' => 10, '2026-11-22' => 20]);
});

it('starts again at 1 after a missed day', function () {
    $this->xp->award($this->user, 10);
    $this->travelTo('2026-11-22 09:00');
    $this->xp->award($this->user, 10);
    $this->travelTo('2026-11-24 09:00');
    $this->xp->award($this->user, 10);

    expect($this->xp->streak($this->user))->toBe(1)
        ->and(app(LearnerState::class)->profile($this->user)->streak_count)->toBe(1);
});

it('shows the streak only while the last day with XP is today or yesterday', function () {
    $this->xp->award($this->user, 10);
    $this->travelTo('2026-11-22 09:00');
    $this->xp->award($this->user, 10);

    $this->travelTo('2026-11-23 21:00');
    expect($this->xp->streak($this->user))->toBe(2)
        ->and($this->xp->today($this->user))->toBe(0);

    $this->travelTo('2026-11-24 00:05');
    expect($this->xp->streak($this->user))->toBe(0);
});

it('counts days on the Romanian calendar', function () {
    // 23:30 UTC on the 21st is already the 22nd in Bucharest.
    $this->travelTo(now('UTC')->setDate(2026, 11, 21)->setTime(23, 30));
    $this->xp->award($this->user, 7);

    expect($this->xp->days($this->user))->toBe(['2026-11-22' => 7])
        ->and(Calendar::todayString())->toBe('2026-11-22');
});

it('converts the day numbers of the original app', function () {
    expect(Calendar::dayNumber('2026-11-21'))->toBe(20778)
        ->and(Calendar::fromDayNumber(20778))->toBe('2026-11-21')
        ->and(Calendar::dayNumber('1970-01-01'))->toBe(0)
        ->and(Calendar::inDays(7))->toBe('2026-11-28');
});
