<?php

namespace App\Services\Content\Validation;

/**
 * The Cambridge mock exams (docs/content/SCHEMA-CAMBRIDGE.md): the exam information and two
 * complete tests per exam, each in the shape of the real papers.
 */
final class CambridgeValidator extends Validator
{
    /** Questions per part of the reading and listening papers, tasks of writing and speaking. */
    private const array SHAPES = [
        'pet' => ['reading' => [5, 5, 5, 5, 6, 6], 'listening' => [7, 6, 6, 6], 'writing' => 3, 'speaking' => 4],
        'fce' => ['reading' => [8, 8, 8, 6, 6, 6, 10], 'listening' => [8, 10, 5, 7], 'writing' => 3, 'speaking' => 4],
    ];

    private const array TESTS = [1, 2];

    public function validate(ValidationReport $report): void
    {
        foreach (self::SHAPES as $exam => $shape) {
            $this->exam($report, $exam, $shape);
        }
    }

    /** @param  array{reading: list<int>, listening: list<int>, writing: int, speaking: int}  $shape */
    private function exam(ValidationReport $report, string $exam, array $shape): void
    {
        $folder = "cambridge/{$exam}";

        if (! is_dir($this->path($folder))) {
            $report->error($folder, 'folder missing');

            return;
        }

        $info = $this->read($report, "{$folder}/exam.json");

        if ($info !== null) {
            $this->info($report, "{$folder}/exam.json", $exam, $info);
        }

        foreach (self::TESTS as $n) {
            $file = "{$folder}/test{$n}.json";
            $test = $this->read($report, $file);

            if ($test !== null) {
                $this->test($report, $file, "{$exam}-test{$n}", $test, $shape);
            }
        }
    }

    /** @return array<array-key, mixed>|null */
    private function read(ValidationReport $report, string $file): ?array
    {
        if (! $this->exists($file)) {
            $report->error($file, 'file missing');

            return null;
        }

        return $this->decode($file, $report);
    }

    /** @param  array<array-key, mixed>  $info */
    private function info(ValidationReport $report, string $file, string $exam, array $info): void
    {
        if (($info['id'] ?? null) !== $exam) {
            $report->error($file, "id must be \"{$exam}\"");
        }

        foreach (['name', 'level', 'aboutRo'] as $key) {
            if (! Value::filled($info[$key] ?? null)) {
                $report->error($file, "missing {$key}");
            }
        }

        $papers = $info['papersRo'] ?? null;
        $valid = Value::isList($papers) && count($papers) >= 4 && array_all(
            $papers,
            fn ($paper) => Value::filled(Value::fields($paper)['name'] ?? null) && Value::filled(Value::fields($paper)['info'] ?? null),
        );

        if (! $valid) {
            $report->error($file, 'papersRo needs >=4 {name, info}');
        }
    }

    /**
     * @param  array<array-key, mixed>  $test
     * @param  array{reading: list<int>, listening: list<int>, writing: int, speaking: int}  $shape
     */
    private function test(ValidationReport $report, string $file, string $id, array $test, array $shape): void
    {
        if (($test['id'] ?? null) !== $id) {
            $report->error($file, "id must be \"{$id}\"");
        }

        if (! Value::filled($test['title'] ?? null)) {
            $report->error($file, 'missing title');
        }

        $this->paper($report, $file, 'reading', $test['reading'] ?? null, $shape['reading'], listening: false);
        $this->paper($report, $file, 'listening', $test['listening'] ?? null, $shape['listening'], listening: true);
        $this->writing($report, $file, $test['writing'] ?? null, $shape['writing']);
        $this->speaking($report, $file, $test['speaking'] ?? null, $shape['speaking']);
    }

    /** @param  list<int>  $shape */
    private function paper(ValidationReport $report, string $file, string $name, mixed $paper, array $shape, bool $listening): void
    {
        $fields = Value::fields($paper);

        if (! Value::truthy($paper) || ! Value::integer($fields['minutes'] ?? null)) {
            $report->error($file, "{$name}: missing or no minutes");

            return;
        }

        $parts = $fields['parts'] ?? null;

        if (! Value::isList($parts) || count($parts) !== count($shape)) {
            $report->error($file, "{$name}: needs ".count($shape).' parts (has '.Value::countLabel($parts).')');

            return;
        }

        foreach ($parts as $i => $part) {
            $this->part($report, $file, "{$name}.parts[{$i}]", Value::fields($part), $shape[$i], $listening);
        }
    }

    /** @param  array<array-key, mixed>  $part */
    private function part(ValidationReport $report, string $file, string $where, array $part, int $expected, bool $listening): void
    {
        if (! Value::filled($part['title'] ?? null) || ! Value::filled($part['instructionsRo'] ?? null)) {
            $report->error($file, "{$where}: needs title + instructionsRo");
        }

        $this->audio($report, $file, $where, $part);
        $this->speakers($report, $file, $where, $part);

        $questions = $part['questions'] ?? null;
        $count = Value::countLabel($questions);

        if (! Value::isList($questions) || count($questions) !== $expected) {
            $report->error($file, "{$where}: needs {$expected} questions (has {$count})");
        }

        $partHasAudio = Value::truthy($part['audio'] ?? null);

        foreach (Value::items($questions) as $i => $question) {
            $this->question($report, $file, "{$where}.questions[{$i}]", $question, $listening, $partHasAudio);
        }

        $answers = [];

        foreach (Value::items($questions) as $question) {
            $question = Value::fields($question);

            if (($question['type'] ?? null) === 'mc') {
                $answers[] = $question['answer'] ?? null;
            }
        }

        if (count($answers) >= 5 && Value::distinct($answers) < 2) {
            $report->warning($file, "{$where}: every correct answer is in the same position");
        }

        // The gaps of a shared text are the questions, one for one.
        $text = $part['text'] ?? null;

        if (Value::filled($text)) {
            $gaps = preg_match_all('/_{3,}/', $text);

            if ($gaps > 0 && $gaps !== Value::length($questions)) {
                $report->warning($file, "{$where}: text has {$gaps} gaps but {$count} questions");
            }
        }
    }

    private function question(ValidationReport $report, string $file, string $where, mixed $question, bool $listening, bool $partHasAudio): void
    {
        if (! is_array($question)) {
            $report->error($file, "{$where}: not an object");

            return;
        }

        if (! Value::filled($question['explainRo'] ?? null)) {
            $report->error($file, "{$where}: missing explainRo");
        }

        $this->audio($report, $file, $where, $question);

        if ($listening && ! $partHasAudio && ! Value::truthy($question['audio'] ?? null)) {
            $report->error($file, "{$where}: listening question without any audio script");
        }

        $this->speakers($report, $file, $where, $question);

        $type = $question['type'] ?? null;

        if ($type === 'mc') {
            $this->multipleChoice($report, $file, $where, $question);
        } elseif ($type === 'fill') {
            $q = $question['q'] ?? null;

            if (! Value::filled($q) || preg_match_all('/_{3,}/', $q) !== 1) {
                $report->error($file, "{$where}: q must contain exactly one ___");
            }

            $answers = $question['answers'] ?? null;

            if (! Value::isList($answers) || $answers === [] || ! array_all($answers, fn ($answer) => Value::filled($answer))) {
                $report->error($file, "{$where}: needs answers[]");
            }
        } else {
            $report->error($file, "{$where}: type must be mc or fill");
        }
    }

    /** @param  array<array-key, mixed>  $question */
    private function multipleChoice(ValidationReport $report, string $file, string $where, array $question): void
    {
        if (! Value::filled($question['q'] ?? null)) {
            $report->error($file, "{$where}: missing q");
        }

        $options = $question['options'] ?? null;

        if (! Value::isList($options) || count($options) < 3 || count($options) > 8 || ! array_all($options, fn ($option) => Value::filled($option))) {
            $report->error($file, "{$where}: needs 3-8 options");

            return;
        }

        if (count(array_unique(array_map(fn ($option) => mb_strtolower(Value::trim(Value::text($option))), $options))) !== count($options)) {
            $report->error($file, "{$where}: duplicate options");
        }

        $answer = $question['answer'] ?? null;

        if (! Value::integer($answer) || $answer < 0 || $answer >= count($options)) {
            $report->error($file, "{$where}: bad answer index");
        }
    }

    /**
     * An audio script (on a part or on one question) is a list of lines {s, en}.
     *
     * @param  array<array-key, mixed>  $holder
     */
    private function audio(ValidationReport $report, string $file, string $where, array $holder): void
    {
        if (! array_key_exists('audio', $holder)) {
            return;
        }

        $lines = Value::items($holder['audio']);
        $valid = $lines !== [] && array_all($lines, fn ($line) => is_array($line)
            && Value::filled($line['s'] ?? null) && Value::filled($line['en'] ?? null));

        if (! $valid) {
            $report->error($file, "{$where}: bad audio script");
        }
    }

    /**
     * The scripts are read aloud with two voices at most.
     *
     * @param  array<array-key, mixed>  $holder
     */
    private function speakers(ValidationReport $report, string $file, string $where, array $holder): void
    {
        $lines = Value::items($holder['audio'] ?? null);

        if (Value::distinct(array_map(fn ($line) => Value::fields($line)['s'] ?? null, $lines)) > 2) {
            $report->error($file, "{$where}: more than 2 speakers");
        }
    }

    private function writing(ValidationReport $report, string $file, mixed $writing, int $expected): void
    {
        $writing = Value::fields($writing);
        $tasks = $writing['tasks'] ?? null;

        if (! Value::integer($writing['minutes'] ?? null) || ! Value::isList($tasks) || count($tasks) !== $expected) {
            $report->error($file, "writing: needs minutes + {$expected} tasks");

            return;
        }

        foreach ($tasks as $i => $task) {
            $where = "writing.tasks[{$i}]";
            $task = Value::fields($task);

            foreach (['title', 'task', 'taskRo', 'model'] as $key) {
                if (! Value::filled($task[$key] ?? null)) {
                    $report->error($file, "{$where}: missing {$key}");
                }
            }

            $this->modelLength($report, $file, $where, $task);

            $checklist = $task['checklistRo'] ?? null;

            if (! Value::isList($checklist) || count($checklist) < 5 || ! array_all($checklist, fn ($item) => Value::filled($item))) {
                $report->error($file, "{$where}: needs >=5 checklistRo");
            }

            $this->usefulPhrases($report, $file, $where, $task);
        }
    }

    /**
     * The model answer may be a little shorter or longer than the task asks (90% to 115%).
     *
     * @param  array<array-key, mixed>  $task
     */
    private function modelLength(ValidationReport $report, string $file, string $where, array $task): void
    {
        $min = $task['minWords'] ?? null;
        $max = $task['maxWords'] ?? null;

        if (! Value::integer($min) || ! Value::integer($max)) {
            $report->error($file, "{$where}: needs minWords + maxWords");

            return;
        }

        $words = Value::wordCount(Value::text($task['model'] ?? null));

        if ($words < $min * 0.9 || $words > $max * 1.15) {
            $report->warning($file, "{$where}: model has {$words} words (limits {$min}-{$max})");
        }
    }

    private function speaking(ValidationReport $report, string $file, mixed $speaking, int $expected): void
    {
        $speaking = Value::fields($speaking);
        $tasks = $speaking['tasks'] ?? null;

        if (! Value::integer($speaking['minutes'] ?? null) || ! Value::isList($tasks) || count($tasks) !== $expected) {
            $report->error($file, "speaking: needs minutes + {$expected} tasks");

            return;
        }

        foreach ($tasks as $i => $task) {
            $where = "speaking.tasks[{$i}]";
            $task = Value::fields($task);

            foreach (['title', 'infoRo', 'model'] as $key) {
                if (! Value::filled($task[$key] ?? null)) {
                    $report->error($file, "{$where}: missing {$key}");
                }
            }

            $prompts = $task['prompts'] ?? null;

            if (! Value::isList($prompts) || $prompts === [] || ! array_all($prompts, fn ($prompt) => Value::filled($prompt))) {
                $report->error($file, "{$where}: needs prompts[]");
            }

            $this->usefulPhrases($report, $file, $where, $task);
        }
    }

    /** @param  array<array-key, mixed>  $task */
    private function usefulPhrases(ValidationReport $report, string $file, string $where, array $task): void
    {
        $phrases = $task['usefulPhrases'] ?? null;
        $valid = Value::isList($phrases) && count($phrases) >= 5 && array_all($phrases, function ($phrase) {
            $phrase = Value::fields($phrase);

            return Value::filled($phrase['en'] ?? null) && Value::filled($phrase['ro'] ?? null);
        });

        if (! $valid) {
            $report->error($file, "{$where}: needs >=5 usefulPhrases {en, ro}");
        }
    }
}
