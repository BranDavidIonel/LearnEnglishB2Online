<?php

namespace App\Enums;

/**
 * The four papers of a Cambridge mock exam. Reading and Listening are marked
 * automatically; Writing and Speaking are self-assessed.
 */
enum CambridgePaper: string
{
    case Reading = 'reading';
    case Listening = 'listening';
    case Writing = 'writing';
    case Speaking = 'speaking';

    /** Marked by the app (and worth more XP) rather than self-assessed. */
    public function isMarked(): bool
    {
        return $this === self::Reading || $this === self::Listening;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
