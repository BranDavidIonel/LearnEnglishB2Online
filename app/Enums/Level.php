<?php

namespace App\Enums;

/**
 * The four levels of the course, in order. The id is the folder of the content
 * (resources/content/a1, …) and the key used everywhere in the progress.
 */
enum Level: string
{
    case A1 = 'a1';
    case A2 = 'a2';
    case B1 = 'b1';
    case B2 = 'b2';

    /** The name shown on chips and badges: "A1". */
    public function label(): string
    {
        return strtoupper($this->value);
    }

    /** English title (a translation key, see lang/ro.json). */
    public function title(): string
    {
        return match ($this) {
            self::A1 => 'Beginner',
            self::A2 => 'Elementary',
            self::B1 => 'Intermediate',
            self::B2 => 'Upper intermediate',
        };
    }

    /** English description (a translation key, see lang/ro.json). */
    public function description(): string
    {
        return match ($this) {
            self::A1 => 'Your first sentences: introducing yourself, your routine, food, the city.',
            self::A2 => 'Simple past and future, shopping, plans, your first conversations with clients.',
            self::B1 => 'Getting by at work and on trips: projects, interviews, emails, stories.',
            self::B2 => 'Fluent English for your career: negotiating, presenting, proposals, idioms.',
        };
    }

    /** The colour of the level in the interface. */
    public function color(): string
    {
        return match ($this) {
            self::A1 => '#22c55e',
            self::A2 => '#38bdf8',
            self::B1 => '#a78bfa',
            self::B2 => '#fb923c',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
