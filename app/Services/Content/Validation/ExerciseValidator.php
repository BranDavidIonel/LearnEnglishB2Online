<?php

namespace App\Services\Content\Validation;

/**
 * The six exercise shapes (mc, fill, order, translate, listen, match), shared by the unit
 * exercises, the reading questions, the unit tests and the placement tests.
 */
final class ExerciseValidator
{
    public function check(ValidationReport $report, string $file, mixed $exercise, string $where, bool $onlyMc = false): void
    {
        if (! is_array($exercise)) {
            $report->error($file, "{$where}: not an object");

            return;
        }

        $type = $exercise['type'] ?? null;

        if ($onlyMc && $type !== 'mc') {
            $report->error($file, "{$where}: must be type \"mc\"");

            return;
        }

        if ($type !== 'match' && ! Value::filled($exercise['explainRo'] ?? null)) {
            $report->error($file, "{$where}: missing explainRo");
        }

        $problems = match ($type) {
            'mc' => $this->multipleChoice($exercise),
            'fill' => $this->fill($exercise),
            'order' => $this->order($exercise),
            'translate' => $this->translate($exercise),
            'listen' => $this->listen($exercise),
            'match' => $this->pairs($exercise),
            default => ['unknown type "'.Value::show($type).'"'],
        };

        foreach ($problems as $problem) {
            $report->error($file, "{$where}: {$problem}");
        }
    }

    /**
     * @param  array<array-key, mixed>  $exercise
     * @return list<string>
     */
    private function multipleChoice(array $exercise): array
    {
        $problems = Value::filled($exercise['q'] ?? null) ? [] : ['missing q'];
        $options = $exercise['options'] ?? null;

        if (! Value::isList($options) || count($options) < 3 || count($options) > 4) {
            return [...$problems, 'needs 3-4 options'];
        }

        if (! $this->allFilled($options)) {
            $problems[] = 'empty option';
        }

        $keys = array_map(fn ($option) => mb_strtolower(Value::trim(Value::text($option))), $options);

        if (count(array_unique($keys)) !== count($options)) {
            $problems[] = 'duplicate options';
        }

        $answer = $exercise['answer'] ?? null;

        if (! Value::integer($answer) || $answer < 0 || $answer >= count($options)) {
            $problems[] = 'bad answer index';
        }

        return $problems;
    }

    /**
     * @param  array<array-key, mixed>  $exercise
     * @return list<string>
     */
    private function fill(array $exercise): array
    {
        $problems = [];
        $q = $exercise['q'] ?? null;

        if (! Value::filled($q) || substr_count($q, '___') !== 1) {
            $problems[] = 'q must contain exactly one ___';
        }

        if (! $this->hasAnswers($exercise)) {
            $problems[] = 'needs answers[]';
        }

        return $problems;
    }

    /**
     * @param  array<array-key, mixed>  $exercise
     * @return list<string>
     */
    private function order(array $exercise): array
    {
        $problems = [];
        $words = $exercise['words'] ?? null;

        if (! Value::isList($words) || count($words) < 4 || count($words) > 9 || ! $this->allFilled($words)) {
            $problems[] = 'words must have 4-9 tokens';
        }

        if (! Value::filled($exercise['ro'] ?? null)) {
            $problems[] = 'missing ro';
        }

        return $problems;
    }

    /**
     * @param  array<array-key, mixed>  $exercise
     * @return list<string>
     */
    private function translate(array $exercise): array
    {
        $problems = Value::filled($exercise['ro'] ?? null) ? [] : ['missing ro'];

        if (! $this->hasAnswers($exercise)) {
            $problems[] = 'needs answers[]';
        }

        return $problems;
    }

    /**
     * @param  array<array-key, mixed>  $exercise
     * @return list<string>
     */
    private function listen(array $exercise): array
    {
        $audio = $exercise['audio'] ?? null;
        $problems = Value::filled($audio) ? [] : ['missing audio'];

        if (! $this->hasAnswers($exercise)) {
            $problems[] = 'needs answers[]';
        } elseif (! in_array($audio, Value::items($exercise['answers']), true)) {
            $problems[] = 'answers must include the audio text';
        }

        return $problems;
    }

    /**
     * @param  array<array-key, mixed>  $exercise
     * @return list<string>
     */
    private function pairs(array $exercise): array
    {
        $pairs = $exercise['pairs'] ?? null;
        $valid = Value::isList($pairs) && count($pairs) === 5 && array_all(
            $pairs,
            fn ($pair) => Value::isList($pair) && count($pair) === 2 && $this->allFilled($pair),
        );

        return $valid ? [] : ['needs exactly 5 [en, ro] pairs'];
    }

    /** @param  array<array-key, mixed>  $exercise */
    private function hasAnswers(array $exercise): bool
    {
        $answers = $exercise['answers'] ?? null;

        return Value::isList($answers) && $answers !== [] && $this->allFilled($answers);
    }

    /** @param  array<array-key, mixed>  $values */
    private function allFilled(array $values): bool
    {
        return array_all($values, fn ($value) => Value::filled($value));
    }
}
