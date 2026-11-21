<?php

namespace App\Enums;

/**
 * The parts of a unit, in the order the learner does them. The first five are the
 * lesson steps (each one counts for the unit progress); the test comes last.
 */
enum UnitStep: string
{
    case Vocab = 'vocab';
    case Grammar = 'grammar';
    case Dialogue = 'dialogue';
    case Practice = 'practice';
    case Reading = 'reading';
    case Test = 'test';

    /**
     * The steps that count for the unit progress (the test is not one of them).
     *
     * @return list<self>
     */
    public static function lessons(): array
    {
        return [self::Vocab, self::Grammar, self::Dialogue, self::Practice, self::Reading];
    }

    /** @return list<string> */
    public static function lessonValues(): array
    {
        return array_map(fn (self $step): string => $step->value, self::lessons());
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
