<?php

use App\Services\Words\MeaningOptions;

/** A word of the index, as much of it as the options need. */
function meaningWord(string $en, string $ro, string $pos = 'noun', string $level = 'a1'): array
{
    return ['en' => $en, 'ro' => $ro, 'pos' => $pos, 'levelId' => $level];
}

it('offers the right meaning and three different others, preferably of the same part of speech', function () {
    $word = meaningWord('dog', 'câine');
    $nouns = array_map(fn (int $i) => meaningWord("noun{$i}", "substantiv {$i}"), range(1, 8));
    $verbs = array_map(fn (int $i) => meaningWord("verb{$i}", "verb {$i}", 'verb'), range(1, 8));

    foreach (range(1, 20) as $round) {
        $options = (new MeaningOptions)->quiz($word, [$word, ...$nouns, ...$verbs]);

        expect($options)->toHaveCount(4)->toContain('câine')
            ->and(array_unique($options))->toHaveCount(4)
            ->and(array_filter($options, fn (string $ro) => str_starts_with($ro, 'verb')))->toBeEmpty();
    }
});

it('takes any part of speech when there are fewer than 8 of the same, and skips repeated meanings', function () {
    $word = meaningWord('dog', 'câine');
    $pool = [meaningWord('hound', 'câine'), meaningWord('cat', 'pisică'), meaningWord('cat2', 'pisică'), meaningWord('run', 'a alerga', 'verb'), meaningWord('big', 'mare', 'adj')];

    $options = (new MeaningOptions)->quiz($word, $pool);

    expect($options)->toHaveCount(4)
        ->and(array_count_values($options))->toBe(array_fill_keys($options, 1))
        ->and($options)->toEqualCanonicalizing(['câine', 'pisică', 'a alerga', 'mare']);
});

it('picks three meanings of other words of the same level for the mini lesson', function () {
    $word = meaningWord('dog', 'câine');
    $words = [$word, meaningWord('hound', 'câine'), meaningWord('cat', 'pisică'), meaningWord('house', 'casă'), meaningWord('tree', 'copac'), meaningWord('car', 'mașină', 'noun', 'a2')];

    $choices = (new MeaningOptions)->lesson($word, $words);

    expect($choices)->toHaveCount(3)
        ->and($choices)->each->toBeIn(['pisică', 'casă', 'copac']);
});
