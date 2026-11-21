<?php

namespace App\Services\Content\Validation;

/**
 * The English sentences of a unit and of a test question, flattened (lower case, no
 * punctuation, nothing in brackets), so a test question that only repeats the unit is caught.
 */
final class UnitSentences
{
    /**
     * Every sentence of three words or more the unit shows: exercises, vocabulary examples,
     * grammar examples, dialogue, reading text and reading questions.
     *
     * @param  array<array-key, mixed>  $unit
     * @return array<string, true>
     */
    public function ofUnit(array $unit): array
    {
        $sentences = [];

        foreach (Value::items($unit['exercises'] ?? null) as $exercise) {
            array_push($sentences, ...$this->ofExercise($exercise));
        }

        foreach (Value::items($unit['vocab'] ?? null) as $word) {
            $sentences[] = Value::fields($word)['ex'] ?? null;
        }

        foreach (Value::items(Value::fields($unit['grammar'] ?? null)['sections'] ?? null) as $section) {
            foreach (Value::items(Value::fields($section)['examples'] ?? null) as $example) {
                $sentences[] = Value::fields($example)['en'] ?? null;
            }
        }

        foreach (Value::items(Value::fields($unit['dialogue'] ?? null)['lines'] ?? null) as $line) {
            $sentences[] = Value::fields($line)['en'] ?? null;
        }

        $reading = Value::fields($unit['reading'] ?? null);
        array_push($sentences, ...(array) preg_split('/(?<=[.!?])'.Value::SPACE.'+/u', Value::text($reading['text'] ?? null)));

        foreach (Value::items($reading['questions'] ?? null) as $question) {
            $sentences[] = Value::fields($question)['q'] ?? null;
        }

        $known = [];

        foreach ($sentences as $sentence) {
            $flat = $this->flatten($sentence);

            if (count(explode(' ', $flat)) >= 3) {
                $known[$flat] = true;
            }
        }

        return $known;
    }

    /**
     * The full English sentence an exercise is about, with the gap filled in.
     *
     * @return list<mixed>
     */
    public function ofExercise(mixed $exercise): array
    {
        $exercise = Value::fields($exercise);
        $answer = $exercise['answer'] ?? null;
        $answer = Value::integer($answer) ? (int) $answer : -1;

        return match ($exercise['type'] ?? null) {
            'mc' => [$this->fillGap($exercise['q'] ?? null, Value::items($exercise['options'] ?? null)[$answer] ?? null)],
            'fill' => [$this->fillGap($exercise['q'] ?? null, Value::items($exercise['answers'] ?? null)[0] ?? null)],
            'order' => [implode(' ', array_map(fn ($word) => Value::text($word), Value::items($exercise['words'] ?? null)))],
            'translate' => [Value::items($exercise['answers'] ?? null)[0] ?? null],
            'listen' => [$exercise['audio'] ?? null],
            default => [],
        };
    }

    /** Lower case, words and apostrophes only, single spaces. */
    public function flatten(mixed $sentence): string
    {
        $text = mb_strtolower(is_string($sentence) ? $sentence : '');
        $text = (string) preg_replace('/\([^)]*\)/u', ' ', $text);
        $text = (string) preg_replace("/[^a-z0-9' ]+/u", ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function fillGap(mixed $question, mixed $answer): string
    {
        $question = Value::text($question);
        $position = strpos($question, '___');

        return $position === false ? $question : substr_replace($question, Value::text($answer), $position, 3);
    }
}
