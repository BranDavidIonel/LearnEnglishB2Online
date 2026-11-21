<?php

namespace App\Services\Progress;

use App\Models\DailyXp;
use App\Models\User;

/**
 * XP and the day streak (original addXp, xpToday, streak). Every XP point counts for
 * today; earning XP on a new day continues the streak when the last day with XP was
 * yesterday and starts it again at 1 otherwise.
 */
class XpService
{
    public function __construct(private readonly LearnerState $state) {}

    /** Adds XP for today and moves the streak on. Zero does nothing at all, like the original. */
    public function award(User $user, int $amount): void
    {
        if ($amount === 0) {
            return;
        }

        $today = Calendar::todayString();
        $profile = $this->state->profile($user);
        $profile->xp += $amount;

        if ($profile->streak_last !== $today) {
            $profile->streak_count = $profile->streak_last === Calendar::inDays(-1) ? $profile->streak_count + 1 : 1;
            $profile->streak_last = $today;
        }

        $profile->save();

        $day = $user->dailyXp()->firstOrNew(['day' => $today]);
        $day->xp = ($day->exists ? $day->xp : 0) + $amount;
        $day->save();
    }

    public function total(User $user): int
    {
        return $this->state->profile($user)->xp;
    }

    public function today(User $user): int
    {
        return (int) DailyXp::query()->where('user_id', $user->id)->where('day', Calendar::todayString())->value('xp');
    }

    /** The streak as shown: it only counts while the last day with XP is today or yesterday. */
    public function streak(User $user): int
    {
        $profile = $this->state->profile($user);

        return in_array($profile->streak_last, [Calendar::todayString(), Calendar::inDays(-1)], true) ? $profile->streak_count : 0;
    }

    /**
     * The XP of every day with XP, oldest first (the progress chart).
     *
     * @return array<string, int> Y-m-d => XP
     */
    public function days(User $user): array
    {
        return DailyXp::query()->where('user_id', $user->id)->orderBy('day')->get()
            ->mapWithKeys(fn (DailyXp $day): array => [$day->day->toDateString() => $day->xp])
            ->all();
    }
}
