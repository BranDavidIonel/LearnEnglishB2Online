<?php

namespace App\Services\Home;

use App\Enums\UnitStep;
use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\CourseProgressService;
use App\Services\Progress\WordReviewService;

/**
 * The pages that lay out the whole course: the learning path (levels, units, exams), the
 * grammar of every unit and the videos. The states come from the progress services; the
 * few lesson details these pages show (a unit's grammar title, its video) are read from
 * the units themselves, since the course outline does not carry them.
 *
 * @phpstan-type Counts array{learned: int, total: int}
 */
class CourseMapService
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly CourseProgressService $course,
        private readonly WordReviewService $words,
    ) {}

    /**
     * Every level of the learning path: its summary (for LevelResource), its units (unit
     * summaries plus the grammar title), how many of its words are learned, whether its
     * open units still have words to learn and how many units wait for review.
     *
     * @return list<array{summary: array<string, mixed>, units: list<array<string, mixed>>, words: Counts, canLearnWords: bool, review: int}>
     */
    public function learnLevels(User $user): array
    {
        $levels = [];

        foreach ($this->content->levels() as $level) {
            $summary = $this->course->levelSummary($user, $level['id']);
            $counts = $this->words->counts($user, $this->words->levelWords($level['id']));

            $levels[] = [
                'summary' => (array) $summary,
                'units' => array_map(fn (array $unit): array => [
                    ...(array) $this->course->unitSummary($user, $unit['id']),
                    'grammar' => $this->grammarOf($unit['id'])['title'] ?? '',
                ], $level['units']),
                'words' => ['learned' => $counts['learned'], 'total' => $counts['total']],
                'canLearnWords' => $this->course->levelUnlocked($user, $level['id']) && $this->words->wordsToLearn($user, $level['id']) !== [],
                'review' => count($this->course->reviewUnits($user, $level['id'])),
            ];
        }

        return $levels;
    }

    /**
     * The grammar list: for every level with units, the title of each unit's grammar and
     * whether the learner has done its grammar step.
     *
     * @return list<array{level: string, units: list<array{id: string, order: int|null, title: string, titleRo: string, done: bool}>}>
     */
    public function grammarList(User $user): array
    {
        $levels = [];

        foreach ($this->content->levels() as $level) {
            if ($level['units'] === []) {
                continue;
            }

            $levels[] = [
                'level' => $level['id'],
                'units' => array_map(function (array $unit) use ($user): array {
                    $grammar = $this->grammarOf($unit['id']);

                    return [
                        'id' => $unit['id'],
                        'order' => $unit['order'],
                        'title' => (string) ($grammar['title'] ?? ''),
                        'titleRo' => (string) ($grammar['titleRo'] ?? ''),
                        'done' => in_array(UnitStep::Grammar->value, $this->course->unitState($user, $unit['id'])['steps'], true),
                    ];
                }, $level['units']),
            ];
        }

        return $levels;
    }

    /**
     * One unit's grammar lesson, or null when there is no such unit.
     *
     * @return array{id: string, order: int|null, grammar: array<string, mixed>}|null
     */
    public function grammarLesson(string $unitId): ?array
    {
        $unit = $this->content->unitOutline($unitId);
        $grammar = $this->grammarOf($unitId);

        if ($unit === null || $grammar === null) {
            return null;
        }

        return ['id' => $unit['id'], 'order' => $unit['order'], 'grammar' => $grammar];
    }

    /**
     * The units that have a video, grouped by level (levels without one are left out).
     *
     * @return list<array{level: string, units: list<array{id: string, order: mixed, title: mixed, level: mixed, grammar: string, video: mixed}>}>
     */
    public function videos(): array
    {
        $levels = [];

        foreach ($this->content->levels() as $level) {
            $units = [];

            foreach ($this->content->units($level['id']) as $unit) {
                if (! empty($unit['video'])) {
                    $units[] = [
                        'id' => (string) $unit['id'],
                        'order' => $unit['order'] ?? null,
                        'title' => $unit['title'] ?? null,
                        'level' => $unit['level'] ?? null,
                        'grammar' => (string) ($unit['grammar']['title'] ?? ''),
                        'video' => $unit['video'],
                    ];
                }
            }

            if ($units !== []) {
                $levels[] = ['level' => $level['id'], 'units' => $units];
            }
        }

        return $levels;
    }

    /** @return array<string, mixed>|null */
    private function grammarOf(string $unitId): ?array
    {
        $grammar = $this->content->unit($unitId)['grammar'] ?? null;

        return is_array($grammar) ? $grammar : null;
    }
}
