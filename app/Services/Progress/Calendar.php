<?php

namespace App\Services\Progress;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The learner's calendar days. "Today" is the local day of the app timezone
 * (Europe/Bucharest), like the original app that used the computer's own date.
 * The original kept review days as day numbers (days since 1970-01-01 of the local
 * date); the database keeps dates, and the import / export converts between the two.
 */
final class Calendar
{
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    /** Today as "Y-m-d". */
    public static function todayString(): string
    {
        return self::today()->toDateString();
    }

    /** The day $days days from today, as "Y-m-d" (negative = in the past). */
    public static function inDays(int $days): string
    {
        return self::today()->addDays($days)->toDateString();
    }

    /** The day number of the original app for a date. */
    public static function dayNumber(CarbonInterface|string $date): int
    {
        $day = $date instanceof CarbonInterface ? $date->toDateString() : substr($date, 0, 10);

        return intdiv(CarbonImmutable::parse($day, 'UTC')->getTimestamp(), 86400);
    }

    /** The date ("Y-m-d") of a day number of the original app. */
    public static function fromDayNumber(int $number): string
    {
        return CarbonImmutable::createFromTimestampUTC($number * 86400)->toDateString();
    }
}
