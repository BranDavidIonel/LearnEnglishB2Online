<?php

use App\Services\Content\Validation\ContentKind;
use App\Services\Content\Validation\ContentValidation;
use App\Services\Content\Validation\Issue;
use App\Services\Content\Validation\Severity;
use App\Services\Content\Validation\SpokenText;

// tests/Fixtures/content/broken/a1/speaking.json breaks the rules of docs/content/SCHEMA-SPEAKING.md one by one.

beforeEach(function () {
    $this->issues = (new ContentValidation)
        ->validate([ContentKind::Speaking], base_path('tests/Fixtures/content/broken'))
        ->issues();
});

it('catches broken speaking content', function (string $message, Severity $severity = Severity::Error) {
    expect($this->issues)->toContainEqual(new Issue('a1/speaking.json', $message, $severity));
})->with([
    'wrong level' => ['level must be "A1"'],
    'wrong number of scenarios' => ['needs exactly 8 scenarios (has 2)'],
    'wrong scenario id' => ['scenarios[0]: id must be "a1-s01"'],
    'scenario field missing' => ['scenarios[0]: missing emoji'],
    'empty closing' => ['scenarios[0]: empty closing'],
    'too few turns' => ['scenarios[0]: needs 6-8 turns (has 5)'],
    'turn field missing' => ['scenarios[1].turns[0]: missing say'],
    'one answer' => ['scenarios[1].turns[0]: needs 2-4 answers'],
    'long answer' => ['scenarios[1].turns[1]: answer has 17 words: "one two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen sixteen seventeen"', Severity::Warning],
    'digits in an answer' => ['scenarios[1].turns[1]: digits in a spoken answer: "I have 2 cats."', Severity::Warning],
    'the same answer twice once spoken' => ['scenarios[1].turns[1]: duplicate answers'],
    'no keywords' => ['scenarios[1].turns[1]: needs 1-3 keywords'],
    'upper-case keyword' => ['scenarios[1].turns[2]: keyword must be lowercase: "Tea"'],
    'keyword not in an answer' => ['scenarios[1].turns[2]: keyword "Tea" is missing from answer "I love coffee."'],
    'wrong number of topics' => ['needs exactly 6 phrase topics (has 2)'],
    'topic field missing' => ['phrases[0]: missing topic'],
    'wrong number of phrases' => ['phrases[0]: needs exactly 10 items (has 9)'],
    'phrase without translation' => ['phrases[1].items[0]: needs en + ro'],
    'one-word phrase' => ['phrases[1].items[1]: 1 words: "Hello"', Severity::Warning],
    'digits in a phrase' => ['phrases[1].items[2]: digits in a spoken phrase: "Call me at 5"', Severity::Warning],
    'phrase repeated once spoken' => ['phrases[1].items[4]: phrase repeated: "What is up"'],
    'wrong number of questions' => ['needs exactly 12 questions (has 1)'],
    'question field missing' => ['questions[0]: missing qRo'],
    'one starter' => ['questions[0]: needs 2-3 starters'],
    'short sample' => ['questions[0]: sample has 1 words (expected 6-24)', Severity::Warning],
]);

it('reports a missing speaking file and broken JSON', function () {
    expect($this->issues)->toContainEqual(new Issue('a2/speaking.json', 'file missing', Severity::Error));

    $issues = (new ContentValidation)
        ->validate([ContentKind::Speaking], base_path('tests/Fixtures/content/malformed'))
        ->issues();

    expect($issues)->toContainEqual(new Issue('a1/speaking.json', 'invalid JSON: Syntax error', Severity::Error));
});

it('compares spoken answers the way the app hears them', function (string $text, string $normalized) {
    expect(SpokenText::normalize($text))->toBe($normalized);
})->with([
    ["I'm fine, thanks!", 'i am fine thanks'],
    ['We can’t — sorry…', 'we can not sorry'],
    ["She's sure they'll say “yes”.", 'she is sure they will say yes'],
    ["Let's go - we won't wait.", 'let us go we will not wait'],
]);
