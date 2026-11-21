<?php

use App\Enums\Theme;
use App\Enums\UnitStep;
use App\Services\Progress\BadgeService;
use App\Services\Progress\HistoryService;
use App\Services\Progress\LearnerSummary;
use App\Services\Progress\MistakeService;
use App\Services\Progress\ResetService;
use App\Services\Progress\SettingsService;
use App\Services\Progress\UnitService;
use App\Services\Progress\WordReviewService;
use App\Services\Progress\XpService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = freshLearner();
});

it('sums up a new learner', function () {
    expect(app(LearnerSummary::class)->for($this->user))->toBe([
        'xp' => 0,
        'xpToday' => 0,
        'goal' => 50,
        'streak' => 0,
        'level' => ['id' => 'a1', 'name' => 'A1', 'color' => '#22c55e'],
        'due' => ['words' => 0, 'verbs' => 0, 'mistakes' => 0],
    ]);
});

it('sums up a learner at work', function () {
    placeAt($this->user, 2);
    app(UnitService::class)->completeStep($this->user, 'b1-u01', UnitStep::Vocab);
    app(WordReviewService::class)->learn($this->user, 'colleague');
    app(XpService::class)->award($this->user, 5);
    app(MistakeService::class)->add($this->user, 'b1-u01:3');
    app(MistakeService::class)->add($this->user, 'b1-u01:r1');
    $this->user->verbReviews()->create(['verb' => 'feel', 'due_on' => '2026-11-20']);
    freshState();

    expect(app(LearnerSummary::class)->for($this->user))->toMatchArray([
        'xp' => 15,
        'xpToday' => 15,
        'streak' => 1,
        'level' => ['id' => 'b1', 'name' => 'B1', 'color' => '#a78bfa'],
        'due' => ['words' => $this->user->wordReviews()->count(), 'verbs' => 1, 'mistakes' => 1],
    ]);
});

it('writes one snapshot a day for the chart, refreshed on every change', function () {
    $history = app(HistoryService::class);
    passUnits($this->user, unitsOf('a1'));
    app(XpService::class)->award($this->user, 30);
    app(WordReviewService::class)->markKnown($this->user, 'hello');
    $this->user->verbReviews()->create(['verb' => 'be', 'box' => 3, 'due_on' => '2026-11-28']);
    freshState();

    $history->snapshot($this->user);
    $history->snapshot($this->user);

    // A1 done by its units only (90%), the road to B1: (0.9 + 0 + 0) / 3.
    expect($history->history($this->user))->toBe(['2026-11-21' => ['xp' => 30, 'w' => 1, 'v' => 1, 'b1' => 30.0, 'b2' => 22.5]]);

    $this->travelTo('2026-11-22 08:00');
    $history->touch($this->user);
    $history->touch($this->user);

    expect(array_keys($history->history($this->user)))->toBe(['2026-11-21', '2026-11-22']);
});

it('makes no snapshot for a learner without progress', function () {
    app(HistoryService::class)->touch($this->user);

    expect($this->user->snapshots()->count())->toBe(0);
});

it('keeps the settings, the goal and the flags', function () {
    $settings = app(SettingsService::class);

    expect($settings->settings($this->user))->toBe(['theme' => 'dark', 'rate' => 0.9, 'voice' => null, 'sound' => true])
        ->and($settings->update($this->user, ['theme' => 'sepia', 'voice' => 'Google UK English Female']))
        ->toBe(['theme' => 'sepia', 'rate' => 0.9, 'voice' => 'Google UK English Female', 'sound' => true])
        ->and($settings->update($this->user, ['rate' => 1.05, 'sound' => false, 'voice' => null]))
        ->toBe(['theme' => 'sepia', 'rate' => 1.05, 'voice' => null, 'sound' => false]);

    $settings->setGoal($this->user, 100);
    $settings->setFlag($this->user, 'keepSkippedWords', true);
    freshState();

    expect($settings->goal($this->user))->toBe(100)
        ->and($settings->flags($this->user))->toBe(['keepSkippedWords' => true])
        ->and($this->user->profile->theme)->toBe(Theme::Sepia);
});

it('resets all progress, settings included', function () {
    app(SettingsService::class)->update($this->user, ['theme' => 'light']);
    app(UnitService::class)->completeStep($this->user, 'a1-u01', UnitStep::Vocab);
    app(MistakeService::class)->add($this->user, 'a1-u01:1');
    app(HistoryService::class)->snapshot($this->user);
    placeAt($this->user, 1);

    app(ResetService::class)->reset($this->user);

    expect(app(LearnerSummary::class)->for($this->user)['xp'])->toBe(0)
        ->and(app(SettingsService::class)->settings($this->user)['theme'])->toBe('dark')
        ->and($this->user->wordReviews()->count() + $this->user->unitProgress()->count() + $this->user->mistakes()->count())->toBe(0)
        ->and($this->user->snapshots()->count() + $this->user->dailyXp()->count())->toBe(0)
        ->and($this->user->placement()->exists())->toBeFalse()
        ->and($this->user->fresh())->not->toBeNull();
});

it('awards the badges', function () {
    $badges = fn () => collect(app(BadgeService::class)->badges($this->user))->where('ok', true)->pluck('name')->all();

    expect(app(BadgeService::class)->badges($this->user))->toHaveCount(23)
        ->and($badges())->toBe([]);

    app(XpService::class)->award($this->user, 1000);
    placeAt($this->user, 2);
    passUnits($this->user, ['b1-u01']);
    app(MistakeService::class)->add($this->user, 'b1-u01:1');
    $this->user->profile()->update(['streak_count' => 7, 'streak_last' => '2026-11-01']);
    freshState();

    expect($badges())->toBe(['First step', 'I know where I start', 'First unit', '3 days in a row', 'One week', '1000 XP', 'Level A1', 'Level A2']);
});

it('translates the badges', function () {
    app()->setLocale('ro');

    expect(app(BadgeService::class)->badges($this->user)[0])->toBe(['icon' => '🚀', 'name' => 'Primul pas', 'desc' => 'Câștigă primele puncte XP', 'ok' => false]);
});
