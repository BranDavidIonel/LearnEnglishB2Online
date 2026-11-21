<?php

use App\Services\Content\Validation\ContentKind;
use App\Services\Content\Validation\ContentValidation;
use App\Services\Content\Validation\Issue;
use App\Services\Content\Validation\Severity;

/*
| tests/Fixtures/content/broken holds tiny units, word banks, tests and placement tests,
| each broken on purpose; every rule of docs/content/SCHEMA.md must be caught there.
*/

beforeEach(function () {
    $this->issues = (new ContentValidation)
        ->validate([ContentKind::Lessons], base_path('tests/Fixtures/content/broken'))
        ->issues();
});

it('catches a broken unit', function (string $file, string $message, Severity $severity = Severity::Error) {
    expect($this->issues)->toContainEqual(new Issue($file, $message, $severity));
})->with([
    'wrong id' => ['a1/unit01.json', 'id must be "a1-u01"'],
    'wrong level' => ['a1/unit01.json', 'level must be "A1"'],
    'wrong order' => ['a1/unit01.json', 'order must be 1'],
    'empty field' => ['a1/unit01.json', 'missing emoji'],
    'missing field' => ['a1/unit01.json', 'missing goalRo'],
    'too few words' => ['a1/unit01.json', 'vocab must have 14-18 items (has 1)'],
    'word without translation' => ['a1/unit01.json', 'vocab[0] missing ro'],
    'unknown part of speech' => ['a1/unit01.json', 'vocab[0] bad pos "animal"'],
    'empty wiki' => ['a1/unit01.json', 'vocab[0] empty wiki'],
    'word repeated in the level' => ['a1/unit03.json', 'vocab "Cat" already used in a1/unit01.json'],
    'grammar without title' => ['a1/unit03.json', 'grammar needs title + titleRo'],
    'too few grammar sections' => ['a1/unit01.json', 'grammar needs 2-5 sections'],
    'section without heading' => ['a1/unit01.json', 'grammar.sections[0] needs headingRo + textRo'],
    'section with one example' => ['a1/unit01.json', 'grammar.sections[0] needs >=2 examples {en, ro}'],
    'table row of the wrong width' => ['a1/unit01.json', 'grammar.sections[0] bad table'],
    'one tip' => ['a1/unit01.json', 'grammar needs >=2 tipsRo'],
    'dialogue too short' => ['a1/unit03.json', 'dialogue needs titleRo + 6-10 lines'],
    'dialogue line without translation' => ['a1/unit01.json', 'dialogue line needs speaker, en, ro'],
    'three speakers' => ['a1/unit01.json', 'dialogue must have exactly 2 speakers'],
    'reading without text' => ['a1/unit03.json', 'reading needs title, text, textRo'],
    'reading too short' => ['a1/unit01.json', 'reading has 4 words (expected 50-110)', Severity::Warning],
    'reading with one question' => ['a1/unit04.json', 'reading needs exactly 4 questions'],
    'reading question that is not mc' => ['a1/unit01.json', 'reading.questions[3]: must be type "mc"'],
    'too few exercises' => ['a1/unit01.json', 'exercises must have 18-20 items (has 2)'],
    'match without five pairs' => ['a1/unit03.json', 'exercises[17]: needs exactly 5 [en, ro] pairs'],
    'too few exercises of a type' => ['a1/unit03.json', 'needs at least 3 "fill" exercises (has 0)'],
    'mc answers always first' => ['a1/unit03.json', 'mc correct answers are not varied enough in position', Severity::Warning],
    'bad video id' => ['a1/unit01.json', 'video needs an 11-char youtubeId + title'],
    'invalid JSON' => ['a1/unit02.json', 'invalid JSON: Syntax error'],
    'missing unit' => ['a1/unit05.json', 'file missing'],
]);

it('catches a broken word bank', function (string $file, string $message, Severity $severity = Severity::Error) {
    expect($this->issues)->toContainEqual(new Issue($file, $message, $severity));
})->with([
    'wrong unit' => ['a1/bank01.json', 'unit must be "a1-u01"'],
    'no words' => ['a1/bank03.json', 'needs a words[] array'],
    'wrong number of words' => ['a1/bank01.json', 'has 5 words, expected 59 (unit total 60)', Severity::Warning],
    'word without fields' => ['a1/bank01.json', 'words[0] missing ipa'],
    'no part of speech' => ['a1/bank01.json', 'words[0] bad pos "null"'],
    'empty wiki' => ['a1/bank01.json', 'words[1] empty wiki'],
    'ipa without slashes' => ['a1/bank01.json', 'words[1] ipa must look like /.../'],
    'spaces around the word' => ['a1/bank01.json', 'words[1] en has extra spaces'],
    'core word of the unit' => ['a1/bank01.json', '"cat" is already a core word in a1/unit01.json'],
    'core word of a later level' => ['a1/bank01.json', '"dog" is already a core word in a2/unit01.json'],
    'repeated in the same bank' => ['a1/bank01.json', '"Apple" is already in a1/bank01.json'],
    'repeated from an earlier level' => ['a2/bank01.json', '"apple" is already in a1/bank01.json'],
    'missing bank' => ['a1/bank04.json', 'file missing (the unit has no word bank)', Severity::Warning],
]);

it('catches a broken unit test', function (string $file, string $message, Severity $severity = Severity::Error) {
    expect($this->issues)->toContainEqual(new Issue($file, $message, $severity));
})->with([
    'wrong unit' => ['a1/test01.json', 'unit must be "a1-u01"'],
    'no questions' => ['a1/test03.json', 'needs a questions[] array'],
    'wrong number of questions' => ['a1/test01.json', 'needs exactly 12 questions (has 13)'],
    'too few of a type' => ['a1/test01.json', 'needs at least 4 "mc" questions (has 3)'],
    'match question' => ['a1/test01.json', 'questions[9]: "match" is not used in tests'],
    'sentence of the unit' => ['a1/test01.json', 'questions[10]: "the cat sleeps on the sofa" already appears in the unit — tests need new sentences'],
    'sentence repeated in the test' => ['a1/test01.json', 'questions[12]: "i like tea" is repeated inside the test'],
    'missing test' => ['a1/test04.json', "file missing (the unit test uses only the unit's exercises)", Severity::Warning],
]);

it('catches a broken exercise', function (string $message) {
    expect($this->issues)->toContainEqual(new Issue('a1/test01.json', $message, Severity::Error));
})->with([
    'not an object' => 'questions[0]: not an object',
    'no explanation' => 'questions[1]: missing explainRo',
    'mc without question' => 'questions[1]: missing q',
    'mc with too few options' => 'questions[1]: needs 3-4 options',
    'mc with an empty option' => 'questions[2]: empty option',
    'mc with the same option twice' => 'questions[2]: duplicate options',
    'mc answer out of range' => 'questions[2]: bad answer index',
    'fill without a gap' => 'questions[3]: q must contain exactly one ___',
    'fill without answers' => 'questions[3]: needs answers[]',
    'order with three words' => 'questions[4]: words must have 4-9 tokens',
    'order without translation' => 'questions[4]: missing ro',
    'translate without Romanian' => 'questions[5]: missing ro',
    'translate with an empty answer' => 'questions[5]: needs answers[]',
    'listen without audio' => 'questions[7]: missing audio',
    'listen audio not accepted' => 'questions[6]: answers must include the audio text',
    'unknown type' => 'questions[8]: unknown type "essay"',
]);

it('catches a broken placement test', function (string $file, string $message) {
    expect($this->issues)->toContainEqual(new Issue($file, $message, Severity::Error));
})->with([
    'wrong level' => ['a1/placement.json', 'bad level'],
    'wrong number of questions' => ['a1/placement.json', 'needs exactly 12 questions'],
    'question that is not mc' => ['a2/placement.json', 'questions[0]: must be type "mc"'],
    'three options' => ['a2/placement.json', 'questions[1]: needs 4 options'],
]);

it('reports a missing level folder', function () {
    expect($this->issues)->toContainEqual(new Issue('b1', 'folder missing', Severity::Error));
});
