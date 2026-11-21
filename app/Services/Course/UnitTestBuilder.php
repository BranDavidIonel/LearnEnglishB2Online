<?php

namespace App\Services\Course;

use Illuminate\Support\Arr;

/**
 * Puts a unit test together, as the original unit page did: 12 questions, half of them
 * new ones (the unit's test questions: the same grammar and words in sentences the learner
 * has not seen), the rest from the unit's exercises ("match" exercises left out). When a
 * unit has fewer new questions, the exercises fill the test up to 12 as far as they can.
 * The questions come in random order. The two numbers are in config/learning.php
 * (rules.unit_test_size and rules.unit_test_new).
 *
 * The mistake keys stay those of the original: "<unit>:<n>" for exercise n,
 * "<unit>:t<n>" for test question n.
 */
class UnitTestBuilder
{
    /** Questions in a unit test (config learning.rules.unit_test_size). */
    public function size(): int
    {
        return (int) config('learning.rules.unit_test_size');
    }

    /**
     * @param  array<string, mixed>  $unit  a whole unit (ContentRepository::unit())
     * @return list<array{ex: array<string, mixed>, unit: string, key: string, fresh: bool}>
     */
    public function build(array $unit): array
    {
        $fresh = $this->pool($unit, 'testItems', 't');
        $seen = array_values(array_filter(
            $this->pool($unit, 'exercises', ''),
            fn (array $item): bool => ($item['ex']['type'] ?? null) !== 'match',
        ));

        $newCount = min((int) config('learning.rules.unit_test_new'), count($fresh));
        $seenCount = min($this->size() - $newCount, count($seen));

        return array_values(Arr::shuffle([
            ...Arr::random($fresh, $newCount),
            ...Arr::random($seen, $seenCount),
        ]));
    }

    /**
     * The questions of one list of the unit with their mistake keys.
     *
     * @param  array<string, mixed>  $unit
     * @return list<array{ex: array<string, mixed>, unit: string, key: string, fresh: bool}>
     */
    private function pool(array $unit, string $list, string $prefix): array
    {
        $id = (string) $unit['id'];
        $items = [];

        foreach ($unit[$list] ?? [] as $index => $exercise) {
            $items[] = ['ex' => $exercise, 'unit' => $id, 'key' => "{$id}:{$prefix}{$index}", 'fresh' => $prefix === 't'];
        }

        return $items;
    }
}
