<?php

use App\Services\Content\ContentFiles;
use App\Services\Content\ContentRepository;
use App\Services\Content\Validation\ContentKind;
use App\Services\Content\Validation\ContentValidation;
use App\Services\Content\Validation\Issue;
use App\Services\Content\Validation\LessonWordsValidator;
use App\Services\Content\Validation\Severity;
use App\Services\Content\WordFinder;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

// tests/Fixtures/content/broken/glossary.json breaks the glossary rules one by one;
// tests/Fixtures/content/words holds one unexplained word in each kind of tapped text.

function glossaryIssues(string $root, ContentKind $kind = ContentKind::Glossary): array
{
    return (new ContentValidation)->validate([$kind], base_path("tests/Fixtures/content/{$root}"))->issues();
}

it('catches a broken glossary entry', function (string $message) {
    expect(glossaryIssues('broken'))->toContainEqual(new Issue('glossary.json', $message, Severity::Error));
})->with([
    'headword repeated' => 'words[1] (start) repeated',
    'meaning missing' => 'words[2] (the) missing ro',
    'unknown part of speech' => 'words[2] (the) bad pos "article"',
    'ipa without slashes' => 'words[2] (the) ipa must look like /…/ (or /…/, /…/)',
    'form of two headwords' => 'words[4] (kid) form "Children" is already a form of "child"',
    'forms not a list' => 'words[5] (good) forms must be a list of words',
    'ipa missing' => 'words[8] (lie) missing ipa',
    'Romanian with cedilla' => 'words[8] (lie) uses ş/ţ with cedilla — use ș/ț',
    'name repeated' => 'names[1] (Cluj) repeated',
    'empty name ipa' => 'names[2] (CRM) empty ipa',
    'name without text' => 'names[3] () missing en',
]);

it('warns about a headword the course explains, unless it is there for its forms', function () {
    $issues = glossaryIssues('broken');

    expect($issues)->toContainEqual(new Issue('glossary.json', 'words[6] (cat) is already a word of the course (a1-u09)', Severity::Warning))
        ->and(array_filter($issues, fn (Issue $issue) => str_contains($issue->message, '(a) ')))->toBe([]);
});

it('only warns when there is no glossary', function () {
    expect(glossaryIssues('none'))->toEqual([
        new Issue('glossary.json', 'file missing (the word window explains only the course vocabulary)', Severity::Warning),
    ]);
});

it('finds the words no content explains, in every tapped text', function () {
    $issues = glossaryIssues('words', ContentKind::Words);
    $warning = fn (string $file, string $word) => new Issue($file, "word \"{$word}\" is explained nowhere (course, irregular verbs, glossary, names)", Severity::Warning);

    // Exercises are not tapped (quokka), nor letters touching a digit or a gap (12th, wait___ed).
    expect($issues)->toEqual([
        $warning('a1/unit01.json', 'zebra'),
        $warning('verbs.json', 'wombat'),
        $warning('a1/speaking.json', 'koala'),
        $warning('a1/speaking.json', 'narwhal'),
        $warning('cambridge/pet/test1.json', 'axolotl'),
        $warning('cambridge/pet/test1.json', 'okapi'),
        $warning('cambridge/pet/test1.json', 'tapir'),
        $warning('cambridge/pet/test1.json', 'dugong'),
    ]);
});

it('lists an unexplained word with its count, source and an example', function () {
    $root = base_path('tests/Fixtures/content/words');
    $finder = new WordFinder(new ContentRepository(new ContentFiles($root), new Repository(new ArrayStore), 0));

    expect((new LessonWordsValidator($root))->unexplained($finder)[0])->toBe([
        'form' => 'zebra',
        'count' => 1,
        'source' => 'lessons',
        'files' => ['a1/unit01.json'],
        'examples' => [['en' => 'The wait___ed zebra.', 'ro' => '']],
    ]);
});

it('finds no unexplained word and no glossary error in the shipped content', function () {
    $issues = (new ContentValidation)->validate([ContentKind::Glossary, ContentKind::Words])->issues();

    expect(array_map(fn (Issue $issue) => "{$issue->file}: {$issue->message}", $issues))->toBe([]);
});
