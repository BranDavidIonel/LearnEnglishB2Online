<?php

use App\Services\Content\Validation\ContentKind;
use App\Services\Content\Validation\ContentValidation;
use App\Services\Content\Validation\Issue;
use App\Services\Content\Validation\Severity;

// tests/Fixtures/content/broken/verbs.json breaks the irregular-verb rules one by one.

function verbIssues(string $root): array
{
    return (new ContentValidation)
        ->validate([ContentKind::Verbs], base_path("tests/Fixtures/content/{$root}"))
        ->issues();
}

it('catches a broken verb', function (string $message) {
    expect(verbIssues('broken'))->toContainEqual(new Issue('verbs.json', $message, Severity::Error));
})->with([
    'field missing' => 'verbs[0] (go) missing v2',
    'unknown level' => 'verbs[0] (go) bad level "C1"',
    'verb repeated' => 'verbs[1] (go) repeated',
    'past simple not in its example' => 'verbs[2] (take) ex2 must use the past simple "took"',
    'past participle not in its example' => 'verbs[2] (take) ex3 must use the past participle "taken"',
    'ipa without slashes' => 'verbs[3] (learn) ipa must look like /…/ – /…/ – /…/',
    'Romanian with cedilla' => 'verbs[3] (learn) uses ş/ţ with cedilla — use ș/ț',
]);

it('accepts an example that uses one of two forms', function () {
    $messages = array_map(fn (Issue $issue) => $issue->message, verbIssues('broken'));

    // verbs[3] is "learnt / learned" and its examples say "learned".
    expect($messages)->not->toContain('verbs[3] (learn) ex2 must use the past simple "learnt / learned"')
        ->not->toContain('verbs[3] (learn) ex3 must use the past participle "learnt / learned"');
});

it('needs a list of verbs', function () {
    expect(verbIssues('malformed'))->toEqual([new Issue('verbs.json', 'needs a verbs[] array', Severity::Error)]);
});

it('only warns when there is no verbs file', function () {
    // There is no tests/Fixtures/content/none: an empty content folder.
    expect(verbIssues('none'))->toEqual([new Issue('verbs.json', 'file missing (no irregular verbs section)', Severity::Warning)]);
});
