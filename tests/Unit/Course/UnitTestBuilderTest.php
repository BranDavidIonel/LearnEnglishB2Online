<?php

use App\Services\Course\UnitTestBuilder;

/**
 * A unit as the content gives it: `$exercises` multiple-choice exercises plus `$matches` "match"
 * exercises, and `$fresh` new test questions.
 *
 * @return array<string, mixed>
 */
function testBuilderUnit(int $exercises, int $fresh, int $matches = 0): array
{
    $mc = fn (string $q): array => ['type' => 'mc', 'q' => $q, 'options' => ['a', 'b'], 'answer' => 0];

    return [
        'id' => 'a1-u01',
        'exercises' => [
            ...array_map(fn (int $n): array => $mc("exercise {$n}"), range(0, $exercises - 1)),
            ...array_fill(0, $matches, ['type' => 'match', 'pairs' => [['a', 'b']]]),
        ],
        'testItems' => $fresh === 0 ? [] : array_map(fn (int $n): array => $mc("new {$n}"), range(0, $fresh - 1)),
    ];
}

/** @param  list<array{key: string, fresh: bool}>  $items */
function testBuilderFresh(array $items): int
{
    return count(array_filter($items, fn (array $item): bool => $item['fresh']));
}

it('builds 12 questions: 6 new ones and 6 from the exercises', function () {
    $items = (new UnitTestBuilder)->build(testBuilderUnit(20, 12, 1));

    expect($items)->toHaveCount(12)
        ->and(testBuilderFresh($items))->toBe(6)
        ->and(array_unique(array_column($items, 'key')))->toHaveCount(12);

    foreach ($items as $item) {
        expect($item['unit'])->toBe('a1-u01')
            ->and($item['ex']['type'])->not->toBe('match')
            ->and($item['key'])->toMatch($item['fresh'] ? '/^a1-u01:t\d+$/' : '/^a1-u01:\d+$/');
    }
});

it('keeps the keys of the original: exercise n and test question n', function () {
    $unit = testBuilderUnit(12, 6);

    foreach ((new UnitTestBuilder)->build($unit) as $item) {
        [, $index] = explode(':', $item['key']);
        $source = $item['fresh'] ? $unit['testItems'][(int) substr($index, 1)] : $unit['exercises'][(int) $index];

        expect($item['ex'])->toBe($source);
    }
});

it('fills the test with exercises when there are fewer new questions', function () {
    $items = (new UnitTestBuilder)->build(testBuilderUnit(20, 2));

    expect($items)->toHaveCount(12)->and(testBuilderFresh($items))->toBe(2);
});

it('takes only exercises when the unit has no new questions', function () {
    $items = (new UnitTestBuilder)->build(testBuilderUnit(20, 0));

    expect($items)->toHaveCount(12)->and(testBuilderFresh($items))->toBe(0);
});

it('never adds "match" exercises, even when the test stays short', function () {
    $items = (new UnitTestBuilder)->build(testBuilderUnit(3, 6, 5));

    expect($items)->toHaveCount(9)
        ->and(testBuilderFresh($items))->toBe(6)
        ->and(array_column(array_column($items, 'ex'), 'type'))->not->toContain('match');
});

it('takes its numbers from the rules', function () {
    config(['learning.rules.unit_test_size' => 8, 'learning.rules.unit_test_new' => 3]);
    $builder = new UnitTestBuilder;
    $items = $builder->build(testBuilderUnit(20, 12));

    expect($builder->size())->toBe(8)
        ->and($items)->toHaveCount(8)
        ->and(testBuilderFresh($items))->toBe(3);
});

it('mixes the questions', function () {
    $orders = [];

    foreach (range(1, 10) as $try) {
        $orders[] = implode(',', array_column((new UnitTestBuilder)->build(testBuilderUnit(6, 6)), 'key'));
    }

    expect(array_unique($orders))->not->toHaveCount(1);
});
