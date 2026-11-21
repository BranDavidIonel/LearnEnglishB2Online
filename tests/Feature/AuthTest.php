<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'learning.admin.email' => 'admin@example.test',
        'learning.admin.password' => 'correct-horse-battery',
        'learning.admin.name' => 'Learner',
    ]);
});

it('sends guests to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('auth.user', null)
            ->where('locale', 'en')
            ->where('locales', ['en', 'ro'])
        );
});

it('logs in the admin made by the seeder from .env', function () {
    $this->seed(DatabaseSeeder::class);

    $this->post(route('login.store'), ['email' => 'admin@example.test', 'password' => 'correct-horse-battery'])
        ->assertRedirect(route('home'));

    $user = User::where('email', 'admin@example.test')->firstOrFail();
    $this->assertAuthenticatedAs($user);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('name', 'English Levels')
            ->where('auth.user', ['id' => $user->id, 'name' => 'Learner', 'email' => 'admin@example.test'])
        );
});

it('refuses a wrong password', function () {
    $this->seed(DatabaseSeeder::class);

    $this->post(route('login.store'), ['email' => 'admin@example.test', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('stops too many login attempts', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['email' => 'admin@example.test', 'password' => 'wrong']);
    }

    $this->post(route('login.store'), ['email' => 'admin@example.test', 'password' => 'wrong'])
        ->assertTooManyRequests();
});

it('sends a signed-in user from the login page to the home page', function () {
    $this->actingAs(User::factory()->create())->get(route('login'))->assertRedirect(route('home'));
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('keeps the language after logging out', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['locale' => 'ro'])
        ->post(route('logout'))
        ->assertSessionHas('locale', 'ro');
});
