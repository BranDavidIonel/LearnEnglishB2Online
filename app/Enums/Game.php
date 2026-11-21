<?php

namespace App\Enums;

/** The word games (each keeps its best result in the progress). */
enum Game: string
{
    // Pairs: the fewer moves, the better.
    case Memory = 'memory';
    // Sprint 60: points in 60 seconds.
    case Sprint = 'sprint';
    // Dictation: words spelled right out of 10.
    case Spell = 'spell';

    /** Memory counts moves, so a smaller number is a better result. */
    public function lowerIsBetter(): bool
    {
        return $this === self::Memory;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
