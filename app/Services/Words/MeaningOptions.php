<?php

namespace App\Services\Words;

/**
 * The wrong meanings shown next to the right one when the learner is asked what a word
 * means: the quiz after a quick sort and the mini lesson of a word. The order is random,
 * as in the original, so every round looks a little different.
 */
class MeaningOptions
{
    /** How many wrong meanings go next to the right one. */
    private const int OTHERS = 3;

    /** Below this many words of the same part of speech, words of any part of speech are used. */
    private const int SAME_POS_MIN = 8;

    /**
     * The right meaning and three different others, shuffled; the others come preferably
     * from words of the same part of speech (the original "meaningOptions").
     *
     * @param  array<string, mixed>  $word
     * @param  list<array<string, mixed>>  $pool  the words to take the others from
     * @return list<string>
     */
    public function quiz(array $word, array $pool): array
    {
        $pool = array_values(array_filter($pool, fn (array $other): bool => $other['en'] !== $word['en']));
        $same = array_values(array_filter($pool, fn (array $other): bool => $other['pos'] === $word['pos']));
        $source = count($same) >= self::SAME_POS_MIN ? $same : $pool;
        shuffle($source);

        $seen = [(string) $word['ro'] => true];
        $others = [];

        foreach ($source as $other) {
            $meaning = (string) $other['ro'];

            if (isset($seen[$meaning])) {
                continue;
            }

            $seen[$meaning] = true;
            $others[] = $meaning;

            if (count($others) === self::OTHERS) {
                break;
            }
        }

        $options = [(string) $word['ro'], ...$others];
        shuffle($options);

        return $options;
    }

    /**
     * Three meanings of other words of the same level, for the "what does it mean?" step of
     * the mini lesson (the right one is added by the page).
     *
     * @param  array<string, mixed>  $word
     * @param  iterable<array<string, mixed>>  $words  the word index
     * @return list<string>
     */
    public function lesson(array $word, iterable $words): array
    {
        $others = [];

        foreach ($words as $other) {
            if ($other['levelId'] === $word['levelId'] && $other['ro'] !== $word['ro']) {
                $others[] = (string) $other['ro'];
            }
        }

        shuffle($others);

        return array_slice($others, 0, self::OTHERS);
    }
}
