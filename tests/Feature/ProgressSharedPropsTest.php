<?php

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shares no learner and no settings with a guest', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('learner', null)->where('settings', null));
});

it('shares the learner and the settings with every page', function () {
    $this->travelTo('2026-11-21 10:00');
    $user = User::factory()->create();
    $user->profile()->create(['xp' => 40, 'theme' => 'sepia', 'streak_count' => 2, 'streak_last' => '2026-11-20']);
    $user->wordReviews()->create(['word' => 'hello', 'due_on' => '2026-11-20']);

    $this->actingAs($user)->get(route('learn'))->assertInertia(fn (Assert $page) => $page
        ->where('learner', [
            'xp' => 40, 'xpToday' => 0, 'goal' => 50, 'streak' => 2,
            'level' => ['id' => 'a1', 'name' => 'A1', 'color' => '#22c55e'],
            'due' => ['words' => 1, 'verbs' => 0, 'mistakes' => 0],
        ])
        ->where('settings', ['theme' => 'sepia', 'rate' => 0.9, 'voice' => null, 'sound' => true]));

    // The first page of the day puts today's point on the progress chart.
    expect($user->snapshots()->sole()->day->toDateString())->toBe('2026-11-21');
});

it('renders every page of the route map', function (string $uri) {
    $this->actingAs(User::factory()->create())->get($uri)->assertOk();
})->with(['/', '/learn', '/progress', '/grammar', '/grammar/a1-u01', '/videos', '/units/a1-u01', '/units/a1-u01/test', '/exams/b2', '/placement',
    '/mistakes', '/flashcards', '/words', '/words/new', '/words/sort/a2', '/units/a1-u01/bank', '/words/verbs', '/games', '/games/spell', '/settings',
    '/speaking', '/speaking/role/a1-u01', '/cambridge', '/cambridge/pet/pet-test1/reading']);

it('remembers the language of the learner for the next login', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $this->actingAs($user)->put(route('locale.update'), ['locale' => 'ro']);
    expect($user->fresh()->locale)->toBe('ro');

    auth()->logout();
    session()->flush();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'secret-password'])->assertSessionHas(SetLocale::SESSION, 'ro');
});
