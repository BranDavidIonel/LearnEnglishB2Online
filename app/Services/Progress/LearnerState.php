<?php

namespace App\Services\Progress;

use App\Models\ExamResult;
use App\Models\LearnerProfile;
use App\Models\Mistake;
use App\Models\Placement;
use App\Models\UnitProgress;
use App\Models\User;
use App\Models\VerbReview;
use App\Models\WordReview;
use Illuminate\Database\Eloquent\Collection;

/**
 * The learner's progress rows, loaded once per request and shared by every progress
 * service (what the original kept in memory as "S.p"). The rules ask for the same rows
 * many times (is this unit unlocked? is that level done?), so each set is read from the
 * database once; the services change the rows they get from here and save them, so what
 * is kept stays true. It is emptied at the end of every request (AppServiceProvider).
 */
class LearnerState
{
    /** @var array<int, array<string, mixed>> user id => part => value */
    private array $loaded = [];

    /** The learner's totals and settings; a new, unsaved row (with the defaults) for a new learner. */
    public function profile(User $user): LearnerProfile
    {
        /** @var LearnerProfile */
        return $this->remember($user, 'profile', fn () => $user->profile()->first() ?? $this->newProfile($user));
    }

    public function placement(User $user): ?Placement
    {
        /** @var Placement|null */
        return $this->remember($user, 'placement', fn () => $user->placement()->first());
    }

    /** @return Collection<string, UnitProgress> by unit id */
    public function units(User $user): Collection
    {
        /** @var Collection<string, UnitProgress> */
        return $this->remember($user, 'units', fn () => $user->unitProgress()->orderBy('id')->get()->keyBy('unit'));
    }

    /**
     * The state of a unit, made (unsaved) when the learner has none yet, like the original
     * unitState(). Use peekUnit() to only read it.
     */
    public function unit(User $user, string $unitId): UnitProgress
    {
        $units = $this->units($user);

        if (! $units->has($unitId)) {
            $unit = new UnitProgress(['unit' => $unitId]);
            $unit->user_id = $user->id;
            $units->put($unitId, $unit);
        }

        /** @var UnitProgress */
        return $units->get($unitId);
    }

    /** The state of a unit without making one: null when the learner never touched it. */
    public function peekUnit(User $user, string $unitId): ?UnitProgress
    {
        return $this->units($user)->get($unitId);
    }

    /** @return Collection<string, ExamResult> by level id */
    public function exams(User $user): Collection
    {
        /** @var Collection<string, ExamResult> */
        return $this->remember($user, 'exams', fn () => $user->examResults()->orderBy('id')->get()->keyBy('level'));
    }

    /** The exam state of a level, made (unsaved) when there is none yet. */
    public function exam(User $user, string $levelId): ExamResult
    {
        $exams = $this->exams($user);

        if (! $exams->has($levelId)) {
            $exam = new ExamResult(['level' => $levelId]);
            $exam->user_id = $user->id;
            $exams->put($levelId, $exam);
        }

        /** @var ExamResult */
        return $exams->get($levelId);
    }

    /** @return Collection<string, WordReview> by English text, in the order they were added */
    public function words(User $user): Collection
    {
        /** @var Collection<string, WordReview> */
        return $this->remember($user, 'words', fn () => $user->wordReviews()->orderBy('id')->get()->keyBy('word'));
    }

    /** @return Collection<string, VerbReview> by base form, in the order they were added */
    public function verbs(User $user): Collection
    {
        /** @var Collection<string, VerbReview> */
        return $this->remember($user, 'verbs', fn () => $user->verbReviews()->orderBy('id')->get()->keyBy('verb'));
    }

    /** @return Collection<int, Mistake> oldest first */
    public function mistakes(User $user): Collection
    {
        /** @var Collection<int, Mistake> */
        return $this->remember($user, 'mistakes', fn () => $user->mistakes()->orderBy('id')->get());
    }

    /** Forgets one part (or everything) of a learner, so it is read again from the database. */
    public function forget(User $user, ?string $part = null): void
    {
        if ($part === null) {
            unset($this->loaded[$user->id]);
        } else {
            unset($this->loaded[$user->id][$part]);
        }
    }

    /** Forgets every learner (end of a request). */
    public function flush(): void
    {
        $this->loaded = [];
    }

    private function remember(User $user, string $part, callable $load): mixed
    {
        if (! array_key_exists($part, $this->loaded[$user->id] ?? [])) {
            $this->loaded[$user->id][$part] = $load();
        }

        return $this->loaded[$user->id][$part];
    }

    private function newProfile(User $user): LearnerProfile
    {
        $profile = new LearnerProfile([
            'goal' => (int) config('learning.rules.goal'),
            'speech_rate' => (float) config('learning.rules.rate.default'),
        ]);
        $profile->user_id = $user->id;

        return $profile;
    }
}
