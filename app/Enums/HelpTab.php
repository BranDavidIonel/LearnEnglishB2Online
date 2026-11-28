<?php

namespace App\Enums;

/** The tabs of the help page: the pronunciation signs, the daily plan, the advice. */
enum HelpTab: string
{
    case Sounds = 'sounds';
    case Plan = 'plan';
    case Tips = 'tips';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
