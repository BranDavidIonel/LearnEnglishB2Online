<?php

namespace App\Enums;

/** The four colour themes of the interface (the style sheet has one block per theme). */
enum Theme: string
{
    case Dark = 'dark';
    case Slate = 'slate';
    case Light = 'light';
    case Sepia = 'sepia';

    /** English name (a translation key, see lang/ro.json). */
    public function label(): string
    {
        return match ($this) {
            self::Dark => 'Dark',
            self::Slate => 'Slate (softer)',
            self::Light => 'Light',
            self::Sepia => 'Sepia (warm)',
        };
    }

    /**
     * Background, panel and accent colour, drawn on the theme picker.
     *
     * @return array{string, string, string}
     */
    public function swatch(): array
    {
        return match ($this) {
            self::Dark => ['#0b1020', '#151c36', '#6366f1'],
            self::Slate => ['#1b1e26', '#272c38', '#7c83f5'],
            self::Light => ['#f6f7fb', '#ffffff', '#4f46e5'],
            self::Sepia => ['#f3ead6', '#fbf5e6', '#a0521d'],
        };
    }

    /**
     * Every theme for the theme picker, its name in the current language.
     *
     * @return list<array{id: string, name: string, swatch: array{string, string, string}}>
     */
    public static function options(): array
    {
        return array_map(fn (self $theme): array => [
            'id' => $theme->value,
            'name' => __($theme->label()),
            'swatch' => $theme->swatch(),
        ], self::cases());
    }
}
