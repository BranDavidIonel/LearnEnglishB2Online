<?php

namespace App\Services\Course;

use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\CourseProgressService;
use App\Services\Progress\ExamService;

/**
 * What the level exam page shows. The page is in one of four states, decided here as
 * the original decided them: "unavailable" (a level without units), "locked" (the level
 * is not open yet), "review" (a failed exam sent units back to review: their tests come
 * first) or "ready" (the exam can be taken; its questions are built on every visit).
 */
class ExamPage
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly CourseProgressService $course,
        private readonly ExamService $exams,
        private readonly UnitPage $units,
    ) {}

    /** @return array<string, mixed> */
    public function data(User $user, string $levelId): array
    {
        /** @var array{id: string, name: string, index: int, units: list<array{id: string, order: int|null, title: string|null, emoji: string|null}>} $level */
        $level = $this->content->level($levelId);
        $previous = $this->content->levels()[$level['index'] - 1] ?? null;
        $next = $this->content->levels()[$level['index'] + 1] ?? null;
        $review = $this->course->reviewUnits($user, $levelId);
        $state = $this->state($user, $levelId, $review);

        return [
            'state' => $state,
            'level' => $this->units->level($levelId),
            'previous' => $previous['name'] ?? null,
            'units' => array_map(
                fn (array $unit): array => ['id' => $unit['id'], 'order' => $unit['order'], 'title' => $unit['title'], 'emoji' => $unit['emoji']],
                $level['units'],
            ),
            'review' => $review,
            'exam' => $this->course->examState($user, $levelId),
            'passed' => $this->course->levelPassed($user, $levelId),
            'notPassed' => count(array_filter(
                $level['units'],
                fn (array $unit): bool => ! $this->course->unitState($user, $unit['id'])['passed'],
            )),
            'questions' => count($level['units']) * (int) config('learning.rules.exam_items_per_unit'),
            'items' => $state === 'ready' ? $this->exams->buildExam($levelId) : [],
            'next' => $next === null ? null : [
                'name' => $next['name'],
                'firstUnit' => $next['units'][0]['id'] ?? null,
            ],
            'passMark' => (float) config('learning.rules.pass'),
        ];
    }

    /** @param  list<string>  $review */
    private function state(User $user, string $levelId, array $review): string
    {
        return match (true) {
            ($this->content->level($levelId)['units'] ?? []) === [] => 'unavailable',
            ! $this->course->levelUnlocked($user, $levelId) => 'locked',
            $review !== [] => 'review',
            default => 'ready',
        };
    }
}
