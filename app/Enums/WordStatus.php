<?php

namespace App\Enums;

/**
 * Where a word (or an irregular verb) is in the spaced repetition: not in the deck yet,
 * being learned, or learned (its box reached config('learning.review.learned_box')).
 */
enum WordStatus: string
{
    case Learned = 'learned';
    case Learning = 'learning';
    case New = 'new';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
