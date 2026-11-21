<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'old-password']);
});

it('sends a guest to the login page', function () {
    $this->put(route('account.profile'), ['name' => 'Bob', 'email' => 'bob@example.com'])->assertRedirect(route('login'));
    $this->put(route('account.password'), [])->assertRedirect(route('login'));
});

it('changes the name and the email', function () {
    $this->actingAs($this->user)
        ->from(route('settings'))
        ->put(route('account.profile'), ['name' => 'Ana Maria', 'email' => 'ana.maria@example.com'])
        ->assertRedirect(route('settings'))
        ->assertSessionHasNoErrors();

    expect($this->user->fresh())->name->toBe('Ana Maria')->email->toBe('ana.maria@example.com');
});

it('keeps the same email and refuses a wrong or taken one', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->user)->put(route('account.profile'), ['name' => 'Ana', 'email' => 'ana@example.com'])->assertSessionHasNoErrors();
    $this->actingAs($this->user)->put(route('account.profile'), ['name' => '', 'email' => 'not an email'])->assertSessionHasErrors(['name', 'email']);
    $this->actingAs($this->user)->put(route('account.profile'), ['name' => 'Ana', 'email' => 'taken@example.com'])->assertSessionHasErrors('email');
    $this->actingAs($this->user)->put(route('account.profile'), ['name' => str_repeat('a', 101), 'email' => 'ana@example.com'])->assertSessionHasErrors('name');

    expect($this->user->fresh()->email)->toBe('ana@example.com');
});

it('changes the password when the current one is right', function () {
    $this->actingAs($this->user)
        ->from(route('settings'))
        ->put(route('account.password'), ['current_password' => 'old-password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertRedirect(route('settings'))
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-password-1', $this->user->fresh()->password))->toBeTrue();
});

it('refuses a new password without the right current one, too short or not repeated', function () {
    $this->actingAs($this->user)
        ->put(route('account.password'), ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertSessionHasErrors('current_password');
    $this->actingAs($this->user)
        ->put(route('account.password'), ['current_password' => 'old-password', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');
    $this->actingAs($this->user)
        ->put(route('account.password'), ['current_password' => 'old-password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-2'])
        ->assertSessionHasErrors('password');

    expect(Hash::check('old-password', $this->user->fresh()->password))->toBeTrue();
});

it('signs in with the new email and password', function () {
    $this->actingAs($this->user)->put(route('account.profile'), ['name' => 'Ana', 'email' => 'new@example.com']);
    $this->actingAs($this->user)->put(route('account.password'), ['current_password' => 'old-password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1']);
    auth()->logout();

    $this->post(route('login.store'), ['email' => 'new@example.com', 'password' => 'new-password-1'])->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($this->user->fresh());
});
