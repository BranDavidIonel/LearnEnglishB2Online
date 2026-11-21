<?php

use App\Models\User;
use App\Services\Verbs\VerbsPage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create();
});

it('sends a guest to the login page', function () {
    $this->get(route('verbs'))->assertRedirect(route('login'));
});

it('shows every verb, the most used first, all new for a new learner', function () {
    $this->actingAs($this->user)->get(route('verbs'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('verbs/Index')
        ->has('verbs', 239)
        ->where('verbs.0.v1', 'be')
        ->where('verbs.0.rank', 1)
        ->where('verbs.0.levelId', 'a1')
        ->where('verbs.0.status', 'new')
        ->where('verbs.0.box', null)
        ->where('verbs.0.alts2', ['was', 'were'])
        ->where('verbs.0.answers2', ['was', 'were', 'was were', 'was/were', 'was / were'])
        ->where('verbs.0.alts3', ['been'])
        ->where('counts', ['learned' => 0, 'learning' => 0, 'new' => 239, 'total' => 239])
        ->where('due', [])
        ->where('wordCounts.learned', 0)
        ->has('wordCounts.total'));
});

it('shows the state of the verbs, the counts and the verbs due today in review order', function () {
    $this->user->verbReviews()->create(['verb' => 'go', 'box' => 3, 'due_on' => '2026-11-28', 'ok' => 3, 'known' => true]);
    $this->user->verbReviews()->create(['verb' => 'come', 'box' => 1, 'due_on' => '2026-11-21', 'ok' => 1]);
    $this->user->verbReviews()->create(['verb' => 'buy', 'box' => 0, 'due_on' => '2026-11-19', 'bad' => 1]);

    $this->actingAs($this->user)->get(route('verbs'))->assertInertia(fn (Assert $page) => $page
        ->where('counts', ['learned' => 1, 'learning' => 2, 'new' => 236, 'total' => 239])
        ->where('due', ['buy', 'come'])
        ->where('verbs.4.v1', 'go')
        ->where('verbs.4.status', 'learned')
        ->where('verbs.4.box', 3)
        ->where('verbs.4.due', '2026-11-28')
        ->where('verbs.4.known', true)
        ->where('verbs.11.v1', 'come')
        ->where('verbs.11.status', 'learning'));
});

it('answers a partial reload with only what it asks for', function () {
    $this->actingAs($this->user)->get(route('verbs'))->assertInertia(fn (Assert $page) => $page
        ->reloadOnly(['counts', 'due'], fn (Assert $reload) => $reload->missing('verbs')->missing('wordCounts')->where('due', [])));
});

it('tells the shape of the three forms', function (string $v1, string $v2, string $v3, string $pattern) {
    expect(VerbsPage::pattern(['v1' => $v1, 'v2' => $v2, 'v3' => $v3]))->toBe($pattern);
})->with([
    ['go', 'went', 'gone', 'abc'],
    ['buy', 'bought', 'bought', 'abb'],
    ['learn', 'learnt / learned', 'learnt / learned', 'abb'],
    ['get', 'got', 'got / gotten', 'abb'],
    ['come', 'came', 'come', 'aba'],
    ['cut', 'cut', 'cut', 'aaa'],
    ['be', 'was / were', 'been', 'abc'],
]);

it('sends the pattern and the accepted answers with each verb', function () {
    $this->actingAs($this->user)->get(route('verbs'))->assertInertia(fn (Assert $page) => $page
        ->where('verbs.56.v1', 'cut')
        ->where('verbs.56.pattern', 'aaa')
        ->where('verbs.68.v1', 'learn')
        ->where('verbs.68.pattern', 'abb')
        ->where('verbs.68.answers3', ['learnt', 'learned', 'learnt learned', 'learnt/learned', 'learnt / learned']));
});
