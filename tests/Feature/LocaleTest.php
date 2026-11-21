<?php

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('uses English by default', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));

    expect(app()->getLocale())->toBe('en');
});

it('switches the language and keeps it in the session', function () {
    $this->from(route('login'))
        ->put(route('locale.update'), ['locale' => 'ro'])
        ->assertRedirect(route('login'))
        ->assertSessionHas(SetLocale::SESSION, 'ro');

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('locale', 'ro'));

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ro'));
});

it('refuses a language the app does not have', function () {
    $this->put(route('locale.update'), ['locale' => 'de'])->assertSessionHasErrors('locale');

    $this->withSession([SetLocale::SESSION => 'de'])
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

it('translates the backend texts', function () {
    $this->withSession([SetLocale::SESSION => 'ro'])
        ->post(route('login.store'), ['email' => 'nobody@example.test', 'password' => 'wrong'])
        ->assertSessionHasErrors(['email' => 'Datele de autentificare nu se potrivesc.']);
});
