<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config(['learning.admin.email' => 'admin@example.test', 'learning.admin.name' => 'Learner']);
});

it('creates the admin from .env and updates it when run again', function () {
    config(['learning.admin.password' => 'first-password']);
    $this->seed(DatabaseSeeder::class);

    config(['learning.admin.password' => 'second-password', 'learning.admin.name' => 'Learner Two']);
    $this->seed(DatabaseSeeder::class);

    $user = User::where('email', 'admin@example.test')->firstOrFail();

    expect(User::count())->toBe(1)
        ->and($user->name)->toBe('Learner Two')
        ->and(Hash::check('second-password', $user->password))->toBeTrue();
});

it('makes a random password when .env has none, and keeps it on the next seed', function () {
    config(['learning.admin.password' => null]);

    $this->seed(DatabaseSeeder::class);
    $hash = User::where('email', 'admin@example.test')->value('password');
    $this->seed(DatabaseSeeder::class);

    expect($hash)->not->toBeEmpty()
        ->and(User::count())->toBe(1)
        ->and(User::where('email', 'admin@example.test')->value('password'))->toBe($hash);
});

it('keeps the credentials out of the code', function () {
    $root = dirname(__DIR__, 2);

    // The seeder and the config read .env; .env.example ships without a password.
    expect(file_get_contents("{$root}/database/seeders/DatabaseSeeder.php"))->toContain("config('learning.admin.password')")
        ->and(file_get_contents("{$root}/config/learning.php"))->toContain("env('ADMIN_PASSWORD')")
        ->and(file_get_contents("{$root}/.env.example"))->toMatch('/^ADMIN_PASSWORD=$/m');
});
