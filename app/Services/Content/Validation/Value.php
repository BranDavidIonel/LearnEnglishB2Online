<?php

namespace App\Services\Content\Validation;

/**
 * Small checks on decoded JSON values. They follow the JavaScript the content was first
 * validated with (what counts as an integer, as blank, as whitespace), so the PHP validators
 * accept and reject exactly the same files.
 */
final class Value
{
    /** Whitespace as JavaScript's \s and trim() see it (Unicode spaces included). */
    public const string SPACE = '[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]';

    /**
     * A string with at least one character that is not whitespace.
     *
     * @phpstan-assert-if-true =string $value
     */
    public static function filled(mixed $value): bool
    {
        return is_string($value) && preg_match('/[^'.substr(self::SPACE, 1, -1).']/u', $value) === 1;
    }

    /**
     * A whole number, written 3 or 3.0 in the JSON.
     *
     * @phpstan-assert-if-true =int|float $value
     */
    public static function integer(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value) && floor($value) === $value);
    }

    /**
     * A JSON array (not an object).
     *
     * @phpstan-assert-if-true =list<mixed> $value
     */
    public static function isList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value);
    }

    /**
     * The elements of a JSON array, or none when the value is anything else.
     *
     * @return list<mixed>
     */
    public static function items(mixed $value): array
    {
        return is_array($value) && array_is_list($value) ? $value : [];
    }

    /**
     * The fields of a JSON object; reading a field of anything else gives nothing.
     *
     * @return array<array-key, mixed>
     */
    public static function fields(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** How many elements a message says a value has: JavaScript's `value ? value.length : 0`. */
    public static function countLabel(mixed $value): string
    {
        if (! self::truthy($value)) {
            return '0';
        }

        return (string) (self::length($value) ?? 'undefined');
    }

    /** Whether a JavaScript `if (value)` would pass. */
    public static function truthy(mixed $value): bool
    {
        return ! in_array($value, [null, false, 0, 0.0, ''], true);
    }

    /** The string itself, or '' for anything that is not a string. */
    public static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /** A value as JavaScript's String() writes it, for messages and word keys. */
    public static function show(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_float($value) && self::integer($value) => (string) (int) $value,
            is_int($value), is_float($value) => (string) $value,
            self::isList($value) => implode(',', array_map(fn ($item) => $item === null ? '' : self::show($item), $value)),
            default => '[object Object]',
        };
    }

    /** JavaScript's `value.length`: characters of a string, elements of an array, null for anything else. */
    public static function length(mixed $value): ?int
    {
        return match (true) {
            is_string($value) => intdiv(strlen((string) mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')), 2),
            self::isList($value) => count($value),
            default => null,
        };
    }

    /** JavaScript's trim(). */
    public static function trim(string $value): string
    {
        return (string) preg_replace('/^'.self::SPACE.'+|'.self::SPACE.'+$/u', '', $value);
    }

    /** The number of words, split on whitespace. */
    public static function wordCount(string $value): int
    {
        return count(array_filter((array) preg_split('/'.self::SPACE.'+/u', $value), fn ($word) => $word !== ''));
    }

    /**
     * How many different values a list holds (values compared as JSON, like a JavaScript Set of primitives).
     *
     * @param  list<mixed>  $values
     */
    public static function distinct(array $values): int
    {
        return count(array_unique(array_map(fn ($value) => json_encode($value), $values)));
    }
}
