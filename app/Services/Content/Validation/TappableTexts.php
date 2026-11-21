<?php

namespace App\Services\Content\Validation;

use App\Services\Content\ContentRepository;

/**
 * Every English text in which the word window is turned on (every word can be tapped), with
 * its Romanian translation when the content has one. This is the one list of those sources:
 *
 * - lessons: unit vocabulary examples, dialogue lines, reading texts, grammar examples, and
 *   the examples of the word banks;
 * - verbs: the example sentences of the irregular verbs (ex2, ex3);
 * - speaking: partner lines, model answers, phrases, open questions and their sample answers;
 * - cambridge: what a mock shows after a paper is handed in or as a model: reading passages
 *   (and the short texts of single questions), listening scripts, the model answers of
 *   writing and speaking.
 */
final class TappableTexts
{
    public const array SOURCES = ['lessons', 'verbs', 'speaking', 'cambridge'];

    public function __construct(private readonly string $root) {}

    /**
     * Every text, in the order of the sources above.
     *
     * @return list<array{file: string, source: string, en: string, ro: string}>
     */
    public function all(): array
    {
        $texts = [];

        foreach (LessonValidator::LEVELS as $level) {
            foreach ($this->files($level, '/^unit\d+\.json$/') as $file) {
                array_push($texts, ...$this->texts($file, 'lessons', $this->unit($this->read($file))));
            }

            foreach ($this->files($level, '/^bank\d+\.json$/') as $file) {
                array_push($texts, ...$this->texts($file, 'lessons', $this->examples(Value::fields($this->read($file))['words'] ?? null)));
            }
        }

        array_push($texts, ...$this->texts('verbs.json', 'verbs', $this->verbs($this->read('verbs.json'))));

        foreach (LessonValidator::LEVELS as $level) {
            $file = "{$level}/speaking.json";
            array_push($texts, ...$this->texts($file, 'speaking', $this->speaking($this->read($file))));
        }

        foreach (ContentRepository::CAMBRIDGE_EXAMS as $exam) {
            foreach ($this->files("cambridge/{$exam}", '/^test\d+\.json$/') as $file) {
                array_push($texts, ...$this->texts($file, 'cambridge', $this->cambridge($this->read($file))));
            }
        }

        return $texts;
    }

    /**
     * @param  list<array{mixed, mixed}>  $pairs  [english, romanian]
     * @return list<array{file: string, source: string, en: string, ro: string}>
     */
    private function texts(string $file, string $source, array $pairs): array
    {
        $texts = [];

        foreach ($pairs as [$en, $ro]) {
            if (Value::filled($en)) {
                $texts[] = ['file' => $file, 'source' => $source, 'en' => $en, 'ro' => Value::text($ro)];
            }
        }

        return $texts;
    }

    /**
     * @param  array<array-key, mixed>  $unit
     * @return list<array{mixed, mixed}>
     */
    private function unit(array $unit): array
    {
        $pairs = $this->examples($unit['vocab'] ?? null);

        foreach (Value::items(Value::fields($unit['grammar'] ?? null)['sections'] ?? null) as $section) {
            foreach (Value::items(Value::fields($section)['examples'] ?? null) as $example) {
                $pairs[] = $this->pair($example, 'en', 'ro');
            }
        }

        foreach (Value::items(Value::fields($unit['dialogue'] ?? null)['lines'] ?? null) as $line) {
            $pairs[] = $this->pair($line, 'en', 'ro');
        }

        $pairs[] = [Value::fields($unit['reading'] ?? null)['text'] ?? null, null];

        return $pairs;
    }

    /** @return list<array{mixed, mixed}> the example sentences of a list of words */
    private function examples(mixed $words): array
    {
        return array_map(fn (mixed $word): array => $this->pair($word, 'ex', 'exRo'), Value::items($words));
    }

    /**
     * @param  array<array-key, mixed>  $file
     * @return list<array{mixed, mixed}>
     */
    private function verbs(array $file): array
    {
        $pairs = [];

        foreach (Value::items($file['verbs'] ?? null) as $verb) {
            $pairs[] = $this->pair($verb, 'ex2', 'ex2Ro');
            $pairs[] = $this->pair($verb, 'ex3', 'ex3Ro');
        }

        return $pairs;
    }

    /**
     * @param  array<array-key, mixed>  $speaking
     * @return list<array{mixed, mixed}>
     */
    private function speaking(array $speaking): array
    {
        $pairs = [];

        foreach (Value::items($speaking['scenarios'] ?? null) as $scenario) {
            foreach (Value::items(Value::fields($scenario)['turns'] ?? null) as $turn) {
                $pairs[] = $this->pair($turn, 'say', 'sayRo');

                foreach (Value::items(Value::fields($turn)['answers'] ?? null) as $answer) {
                    $pairs[] = [$answer, null];
                }
            }
        }

        foreach (Value::items($speaking['phrases'] ?? null) as $topic) {
            foreach (Value::items(Value::fields($topic)['items'] ?? null) as $phrase) {
                $pairs[] = $this->pair($phrase, 'en', 'ro');
            }
        }

        foreach (Value::items($speaking['questions'] ?? null) as $question) {
            $pairs[] = $this->pair($question, 'q', 'qRo');
            $pairs[] = [Value::fields($question)['sample'] ?? null, null];
        }

        return $pairs;
    }

    /**
     * @param  array<array-key, mixed>  $test
     * @return list<array{mixed, mixed}>
     */
    private function cambridge(array $test): array
    {
        $pairs = [];

        foreach (Value::items(Value::fields($test['reading'] ?? null)['parts'] ?? null) as $part) {
            $pairs[] = [Value::fields($part)['text'] ?? null, null];

            foreach (Value::items(Value::fields($part)['questions'] ?? null) as $question) {
                $pairs[] = [Value::fields($question)['text'] ?? null, null];
            }
        }

        foreach (Value::items(Value::fields($test['listening'] ?? null)['parts'] ?? null) as $part) {
            array_push($pairs, ...$this->script(Value::fields($part)['audio'] ?? null));

            foreach (Value::items(Value::fields($part)['questions'] ?? null) as $question) {
                array_push($pairs, ...$this->script(Value::fields($question)['audio'] ?? null));
            }
        }

        foreach (['writing', 'speaking'] as $paper) {
            foreach (Value::items(Value::fields($test[$paper] ?? null)['tasks'] ?? null) as $task) {
                $pairs[] = [Value::fields($task)['model'] ?? null, null];
            }
        }

        return $pairs;
    }

    /** @return list<array{mixed, mixed}> the lines of a listening script */
    private function script(mixed $lines): array
    {
        return array_map(fn (mixed $line): array => $this->pair($line, 'en', 'ro'), Value::items($lines));
    }

    /** @return array{mixed, mixed} */
    private function pair(mixed $item, string $en, string $ro): array
    {
        $item = Value::fields($item);

        return [$item[$en] ?? null, $item[$ro] ?? null];
    }

    /** @return list<string> */
    private function files(string $folder, string $pattern): array
    {
        $names = array_map('basename', glob("{$this->root}/{$folder}/*.json") ?: []);
        $names = array_values(array_filter($names, fn (string $name): bool => preg_match($pattern, $name) === 1));
        sort($names);

        return array_map(fn (string $name): string => "{$folder}/{$name}", $names);
    }

    /** @return array<array-key, mixed> */
    private function read(string $file): array
    {
        $path = "{$this->root}/{$file}";

        return Value::fields(is_file($path) ? json_decode((string) file_get_contents($path), true) : null);
    }
}
