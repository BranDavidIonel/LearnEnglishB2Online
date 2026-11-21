<?php

use App\Services\Content\Validation\ContentValidation;
use App\Services\Content\Validation\Issue;
use App\Services\Content\Validation\Severity;

// Protects every content edit: the course that ships must pass php artisan content:validate.
it('finds no error in the shipped content', function () {
    $report = (new ContentValidation)->validate();
    $errors = array_filter($report->issues(), fn (Issue $issue) => $issue->severity === Severity::Error);

    expect($errors)->toBe([])
        ->and($report->fileCount())->toBeGreaterThan(100);
});

it('passes on the shipped content', function () {
    $this->artisan('content:validate')
        ->expectsOutputToContain('0 errors')
        ->assertSuccessful();
});

it('fails on broken content and names the file and the place', function () {
    config(['learning.content_path' => base_path('tests/Fixtures/content/broken')]);

    $this->artisan('content:validate')
        ->expectsOutputToContain('a1/test01.json')
        ->expectsOutputToContain('questions[2]: bad answer index')
        ->expectsOutputToContain('a1/speaking.json')
        ->expectsOutputToContain('verbs[1] (go) repeated')
        ->assertFailed();
});

it('checks one kind of content only', function () {
    config(['learning.content_path' => base_path('tests/Fixtures/content/broken')]);

    $this->artisan('content:validate', ['--only' => ['verbs']])
        ->expectsOutputToContain('verbs.json')
        ->doesntExpectOutputToContain('a1/unit01.json')
        ->expectsOutputToContain('Checked 1 file: 14 errors, 0 warnings.')
        ->assertFailed();
});

it('accepts several kinds separated by commas', function () {
    $this->artisan('content:validate', ['--only' => ['verbs,speaking']])
        ->expectsOutputToContain('Checked 5 files: 0 errors, 0 warnings.')
        ->assertSuccessful();
});

it('refuses an unknown kind of content', function () {
    $this->artisan('content:validate', ['--only' => ['grammar']])
        ->expectsOutputToContain('Unknown kind of content')
        ->assertExitCode(2);
});
