<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com']);
});

it('sends a guest to the login page', function () {
    $this->get(route('settings'))->assertRedirect(route('login'));
});

it('shows the settings page with the settings, the goal and the account', function () {
    $this->user->profile()->create(['theme' => 'sepia', 'goal' => 100, 'speech_rate' => 1.05, 'sound' => false]);

    $this->actingAs($this->user)->get(route('settings'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Settings')
        ->where('goals', [20, 50, 100, 150])
        ->where('settings', ['theme' => 'sepia', 'rate' => 1.05, 'voice' => null, 'sound' => false])
        ->where('learner.goal', 100)
        ->where('auth.user.name', 'Ana')
        ->where('auth.user.email', 'ana@example.com'));
});

it('restores a downloaded progress file and resets everything, as the page does', function () {
    $this->actingAs($this->user);
    $this->patchJson(route('api.settings.update'), ['theme' => 'light']);
    $this->postJson(route('api.verbs.grade'), ['verbs' => ['go'], 'grade' => 2]);
    $this->postJson(route('api.xp.store'), ['amount' => 12]);

    // "Download my progress", then the progress changes …
    $file = $this->get(route('api.progress.export'))->assertDownload('english-levels-progress.json')->streamedContent();
    $this->postJson(route('api.xp.store'), ['amount' => 30]);
    $this->postJson(route('api.verbs.forget'), ['verbs' => ['go']]);

    // … "Restore from a file" brings back what was downloaded.
    $this->postJson(route('api.progress.import'), ['content' => $file])
        ->assertOk()
        ->assertJsonPath('settings.theme', 'light')
        ->assertJsonPath('learner.xp', 12);
    expect($this->user->verbReviews()->where('verb', 'go')->value('box'))->toBe(1);

    $this->get(route('settings'))->assertInertia(fn (Assert $page) => $page->where('settings.theme', 'light')->where('learner.xp', 12));

    // A file that is not a progress file changes nothing.
    $this->postJson(route('api.progress.import'), ['content' => '{"hello": true'])->assertUnprocessable();
    expect($this->user->verbReviews()->count())->toBe(1);

    // "Delete all progress".
    $this->deleteJson(route('api.progress.destroy'))->assertOk()->assertJsonPath('settings.theme', 'dark');
    $this->get(route('settings'))->assertInertia(fn (Assert $page) => $page
        ->where('settings', ['theme' => 'dark', 'rate' => 0.9, 'voice' => null, 'sound' => true])
        ->where('learner.xp', 0)
        ->where('learner.goal', 50));
    expect($this->user->verbReviews()->count())->toBe(0);
});
