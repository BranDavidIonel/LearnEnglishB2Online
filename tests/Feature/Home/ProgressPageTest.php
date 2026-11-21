<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create();
});

it('sends guests to the login page', function () {
    $this->get(route('progress'))->assertRedirect(route('login'));
});

it('sends an empty chart and every badge to a new learner', function () {
    $this->actingAs($this->user)->get(route('progress'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Progress')
        ->where('chart', ['days' => [], 'history' => [], 'goal' => 50])
        ->where('stats.units', ['passed' => 0, 'total' => 40])
        ->where('stats.words.learned', 0)
        ->where('stats.verbs.learned', 0)
        ->has('badges', 23)
        ->where('badges.0', ['icon' => '🚀', 'name' => 'First step', 'desc' => 'Earn your first XP', 'ok' => false])
        ->has('road.levels', 4));
});

it('sends the XP of every day, the daily snapshots and the totals', function () {
    $this->user->profile()->create(['xp' => 60, 'goal' => 100]);
    $this->user->dailyXp()->create(['day' => '2026-11-19', 'xp' => 35]);
    $this->user->dailyXp()->create(['day' => '2026-11-20', 'xp' => 25]);
    $this->user->snapshots()->create(['day' => '2026-11-19', 'xp' => 35]);
    $this->user->unitProgress()->create(['unit' => 'a1-u01', 'passed' => true]);
    $this->user->unitProgress()->create(['unit' => 'a1-u02', 'passed' => true, 'review' => true]);
    $this->user->wordReviews()->create(['word' => 'hello', 'box' => 3, 'due_on' => '2026-11-28']);
    $this->user->wordReviews()->create(['word' => 'name', 'box' => 1, 'due_on' => '2026-11-22']);

    $this->actingAs($this->user)->get(route('progress'))->assertInertia(fn (Assert $page) => $page
        ->where('chart.days', ['2026-11-19' => 35, '2026-11-20' => 25])
        ->where('chart.goal', 100)
        ->where('chart.history.2026-11-19', ['xp' => 35])
        // Today's point is made when the page is opened, so the line ends on today.
        ->where('chart.history.2026-11-21.xp', 60)
        ->where('chart.history.2026-11-21.w', 1)
        ->where('stats.units', ['passed' => 2, 'total' => 40])
        ->where('stats.words.learned', 1)
        ->where('stats.words.learning', 1)
        ->where('badges.0.ok', true)
        ->where('badges.2.ok', true));
});

it('shows the badges in the interface language', function () {
    $this->actingAs($this->user)->withSession(['locale' => 'ro'])->get(route('progress'))
        ->assertInertia(fn (Assert $page) => $page->where('badges.0.name', 'Primul pas'));
});
