<?php

use App\Models\User;
use App\Services\Progress\LearnerState;

/*
| Small helpers for the tests of the progress rules: they write rows straight into the
| database (as if the learner had done the work) and forget what the services had loaded.
*/

/** A learner with no progress at all. */
function freshLearner(): User
{
    return User::factory()->create();
}

/** Forgets the rows the services loaded, so the next call reads the database again. */
function freshState(): void
{
    app(LearnerState::class)->flush();
}

/**
 * Units in a given state, e.g. passUnits($user, ['a1-u01', 'a1-u02']).
 *
 * @param  list<string>  $units
 * @param  array<string, mixed>  $state
 */
function passUnits(User $user, array $units, array $state = []): void
{
    foreach ($units as $unit) {
        $user->unitProgress()->updateOrCreate(
            ['unit' => $unit],
            ['steps' => ['vocab', 'grammar', 'dialogue', 'practice', 'reading'], 'best' => 0.9, 'passed' => true, 'review' => false, 'tries' => 1, ...$state],
        );
    }

    freshState();
}

/** The ten units of a level, "a1-u01" … "a1-u10". */
function unitsOf(string $level): array
{
    return array_map(fn (int $n): string => sprintf('%s-u%02d', $level, $n), range(1, 10));
}

function passExam(User $user, string $level): void
{
    $user->examResults()->updateOrCreate(
        ['level' => $level],
        ['best' => 0.9, 'passed' => true, 'tries' => 1, 'last_taken_on' => now()->toDateString()],
    );
    freshState();
}

/** A placement test result that starts the learner at the level of index $start. */
function placeAt(User $user, int $start): void
{
    $user->placement()->updateOrCreate(
        [],
        ['start_index' => $start, 'scores' => ['a1' => 1], 'taken_on' => now()->toDateString()],
    );
    freshState();
}
