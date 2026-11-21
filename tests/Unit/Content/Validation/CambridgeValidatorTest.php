<?php

use App\Services\Content\Validation\ContentKind;
use App\Services\Content\Validation\ContentValidation;
use App\Services\Content\Validation\Issue;
use App\Services\Content\Validation\Severity;

// tests/Fixtures/content/broken/cambridge breaks the rules of docs/content/SCHEMA-CAMBRIDGE.md one by one.

beforeEach(function () {
    $this->issues = (new ContentValidation)
        ->validate([ContentKind::Cambridge], base_path('tests/Fixtures/content/broken'))
        ->issues();
});

it('catches a broken exam description', function (string $message) {
    expect($this->issues)->toContainEqual(new Issue('cambridge/pet/exam.json', $message, Severity::Error));
})->with([
    'wrong id' => 'id must be "pet"',
    'field missing' => 'missing name',
    'too few papers' => 'papersRo needs >=4 {name, info}',
]);

it('catches a broken mock test', function (string $file, string $message, Severity $severity = Severity::Error) {
    expect($this->issues)->toContainEqual(new Issue("cambridge/pet/{$file}", $message, $severity));
})->with([
    'wrong id' => ['test1.json', 'id must be "pet-test1"'],
    'no title' => ['test1.json', 'missing title'],
    'paper without minutes' => ['test1.json', 'reading: missing or no minutes'],
    'wrong number of parts' => ['test1.json', 'listening: needs 4 parts (has 0)'],
    'no writing tasks' => ['test1.json', 'writing: needs minutes + 3 tasks'],
    'no speaking tasks' => ['test1.json', 'speaking: needs minutes + 4 tasks'],
    'part without title' => ['test2.json', 'listening.parts[0]: needs title + instructionsRo'],
    'three voices in a part' => ['test2.json', 'listening.parts[0]: more than 2 speakers'],
    'wrong number of questions' => ['test2.json', 'listening.parts[0]: needs 7 questions (has 6)'],
    'answers always first' => ['test2.json', 'listening.parts[0]: every correct answer is in the same position', Severity::Warning],
    'gaps that are not the questions' => ['test2.json', 'listening.parts[0]: text has 2 gaps but 6 questions', Severity::Warning],
    'empty part script' => ['test2.json', 'listening.parts[1]: bad audio script'],
    'question not an object' => ['test2.json', 'listening.parts[1].questions[0]: not an object'],
    'the same option twice' => ['test2.json', 'listening.parts[1].questions[1]: duplicate options'],
    'answer out of range' => ['test2.json', 'listening.parts[1].questions[1]: bad answer index'],
    'no explanation' => ['test2.json', 'listening.parts[1].questions[2]: missing explainRo'],
    'mc without question' => ['test2.json', 'listening.parts[1].questions[2]: missing q'],
    'two options' => ['test2.json', 'listening.parts[1].questions[2]: needs 3-8 options'],
    'fill with two gaps' => ['test2.json', 'listening.parts[1].questions[3]: q must contain exactly one ___'],
    'fill without answers' => ['test2.json', 'listening.parts[1].questions[3]: needs answers[]'],
    'unknown type' => ['test2.json', 'listening.parts[1].questions[4]: type must be mc or fill'],
    'question script line without text' => ['test2.json', 'listening.parts[1].questions[5]: bad audio script'],
    'listening question without script' => ['test2.json', 'listening.parts[2].questions[0]: listening question without any audio script'],
    'three voices in a question' => ['test2.json', 'listening.parts[2].questions[1]: more than 2 speakers'],
    'writing field missing' => ['test2.json', 'writing.tasks[0]: missing model'],
    'no word limits' => ['test2.json', 'writing.tasks[0]: needs minWords + maxWords'],
    'short checklist' => ['test2.json', 'writing.tasks[0]: needs >=5 checklistRo'],
    'few writing phrases' => ['test2.json', 'writing.tasks[0]: needs >=5 usefulPhrases {en, ro}'],
    'model of the wrong length' => ['test2.json', 'writing.tasks[1]: model has 3 words (limits 100-120)', Severity::Warning],
    'speaking field missing' => ['test2.json', 'speaking.tasks[0]: missing infoRo'],
    'no prompts' => ['test2.json', 'speaking.tasks[0]: needs prompts[]'],
    'few speaking phrases' => ['test2.json', 'speaking.tasks[0]: needs >=5 usefulPhrases {en, ro}'],
]);

it('does not flag a model answer within its limits', function () {
    $messages = array_map(fn (Issue $issue) => $issue->message, $this->issues);

    expect($messages)->not->toContain('writing.tasks[2]: model has 100 words (limits 100-120)');
});

it('reports missing tests and exam folders', function () {
    expect($this->issues)->toContainEqual(new Issue('cambridge/fce/test1.json', 'file missing', Severity::Error));

    $issues = (new ContentValidation)
        ->validate([ContentKind::Cambridge], base_path('tests/Fixtures/content/malformed'))
        ->issues();

    expect($issues)->toContainEqual(new Issue('cambridge/pet', 'folder missing', Severity::Error));
});
