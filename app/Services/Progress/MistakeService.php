<?php

namespace App\Services\Progress;

use App\Models\Mistake;
use App\Models\User;
use App\Services\Content\ContentRepository;

/**
 * "My mistakes": the questions answered wrong, until they are answered right. The list
 * keeps the latest 200 (the oldest goes first). A key points into the content:
 * "<unit>:<n>" is exercise n of the unit, "<unit>:t<n>" question n of its test,
 * "<unit>:r<n>" question n of its reading (kept, but not shown: it needs its text).
 */
class MistakeService
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly LearnerState $state,
    ) {}

    /** Adds a question to the list (once); returns false when it was already there. */
    public function add(User $user, string $key): bool
    {
        $mistakes = $this->state->mistakes($user);

        if ($key === '' || $mistakes->contains('key', $key)) {
            return false;
        }

        $mistake = $user->mistakes()->create(['key' => $key]);
        $mistakes->push($mistake);

        while ($mistakes->count() > (int) config('learning.rules.mistakes_limit')) {
            $mistakes->shift()?->delete();
        }

        return true;
    }

    /** Takes a question off the list; returns false when it was not there. */
    public function clear(User $user, string $key): bool
    {
        $mistakes = $this->state->mistakes($user);
        $index = $mistakes->search(fn (Mistake $mistake): bool => $mistake->key === $key);

        if ($index === false) {
            return false;
        }

        $mistakes->pull($index)?->delete();

        return true;
    }

    /**
     * The keys of the list, oldest first.
     *
     * @return list<string>
     */
    public function keys(User $user): array
    {
        return array_values($this->state->mistakes($user)->map(fn (Mistake $mistake): string => $mistake->key)->all());
    }

    /**
     * The questions of the list that can be practised: {ex, unit, key}, oldest first.
     * Keys of units that no longer exist, of questions that are gone and of reading
     * questions are left out.
     *
     * @return list<array{ex: array<string, mixed>, unit: string, key: string}>
     */
    public function items(User $user): array
    {
        $items = [];

        foreach ($this->keys($user) as $key) {
            $exercise = $this->resolve($key);

            if ($exercise !== null) {
                $items[] = ['ex' => $exercise, 'unit' => explode(':', $key)[0], 'key' => $key];
            }
        }

        return $items;
    }

    /**
     * How many questions can be practised (the badge of the menu). Reads only the outline
     * of the course, so it stays cheap on every page.
     */
    public function count(User $user): int
    {
        $count = 0;

        foreach ($this->keys($user) as $key) {
            $target = $this->target($key);
            $unit = $target === null ? null : $this->content->unitOutline($target['unit']);

            if ($unit !== null && $target['index'] < $unit[$target['list']]) {
                $count++;
            }
        }

        return $count;
    }

    /** @return array<string, mixed>|null */
    private function resolve(string $key): ?array
    {
        $target = $this->target($key);
        $unit = $target === null ? null : $this->content->unit($target['unit']);
        $exercise = $unit === null ? null : ($unit[$target['list']][$target['index']] ?? null);

        return is_array($exercise) ? $exercise : null;
    }

    /**
     * Where a key points: the unit, the list (exercises or testItems) and the index.
     * Null for reading questions and keys that do not point anywhere.
     *
     * @return array{unit: string, list: 'exercises'|'testItems', index: int}|null
     */
    private function target(string $key): ?array
    {
        $parts = explode(':', $key);

        if (count($parts) < 2 || str_starts_with($parts[1], 'r')) {
            return null;
        }

        $isTest = str_starts_with($parts[1], 't');
        $number = $isTest ? substr($parts[1], 1) : $parts[1];

        // An empty number is 0, as Number('') was in the original.
        if ($number !== '' && ! ctype_digit($number)) {
            return null;
        }

        return ['unit' => $parts[0], 'list' => $isTest ? 'testItems' : 'exercises', 'index' => (int) $number];
    }
}
