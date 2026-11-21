<?php

namespace App\Services\Course;

use App\Enums\Level;
use App\Enums\UnitStep;
use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\CourseProgressService;
use App\Services\Progress\SpeakingService;
use App\Services\Progress\WordReviewService;

/**
 * What the unit pages show: the overview (the six steps, the word bank, the role-play
 * bonus, the video) and each step with the content it needs. Only the part of the unit
 * that the page draws is sent.
 */
class UnitPage
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly CourseProgressService $course,
        private readonly WordReviewService $words,
        private readonly SpeakingService $speaking,
        private readonly UnitTestBuilder $tests,
    ) {}

    /** Why the unit cannot be opened ("missing" or "locked"), or null when it can. */
    public function blocked(User $user, string $unitId): ?string
    {
        if ($this->content->unitOutline($unitId) === null) {
            return 'missing';
        }

        return $this->course->unitUnlocked($user, $unitId) ? null : 'locked';
    }

    /** @return array<string, mixed> */
    public function overview(User $user, string $unitId): array
    {
        /** @var array<string, mixed> $unit */
        $unit = $this->content->unit($unitId);

        return [
            'unit' => [
                ...$this->head($user, $unitId),
                'titleRo' => $unit['titleRo'],
                'goalRo' => $unit['goalRo'],
                'grammarTitle' => $unit['grammar']['titleRo'],
                'video' => $unit['video'] ?? null,
            ],
            'level' => $this->level((string) $unit['levelId']),
            'bank' => $this->bank($user, $unitId),
            'rolePlay' => $this->speaking->state($user)['scen']["role-{$unitId}"] ?? null,
            'passMark' => (float) config('learning.rules.pass'),
        ];
    }

    /** @return array<string, mixed> */
    public function step(User $user, string $unitId, UnitStep $step): array
    {
        /** @var array<string, mixed> $unit */
        $unit = $this->content->unit($unitId);

        return [
            'unit' => $this->head($user, $unitId),
            'level' => $this->level((string) $unit['levelId']),
            'step' => $step->value,
            'passMark' => (float) config('learning.rules.pass'),
            ...$this->stepContent($user, $unit, $step),
        ];
    }

    /**
     * The unit as the step head and the result cards need it.
     *
     * @return array{id: string, order: int|null, title: string|null, emoji: string|null, levelId: string, index: int, steps: array<string, bool>, passed: bool, review: bool, best: float, tries: int}
     */
    public function head(User $user, string $unitId): array
    {
        /** @var array{id: string, order: int|null, title: string|null, emoji: string|null, levelId: string, index: int} $outline */
        $outline = $this->content->unitOutline($unitId);
        $state = $this->course->unitState($user, $unitId);

        return [
            'id' => $outline['id'],
            'order' => $outline['order'],
            'title' => $outline['title'],
            'emoji' => $outline['emoji'],
            'levelId' => $outline['levelId'],
            'index' => $outline['index'],
            'steps' => array_fill_keys($state['steps'], true),
            'passed' => $state['passed'],
            'review' => $state['review'],
            'best' => $state['best'],
            'tries' => $state['tries'],
        ];
    }

    /**
     * A level as the course pages colour and name it.
     *
     * @return array{id: string, name: string, title: string, color: string}
     */
    public function level(string $levelId): array
    {
        $level = Level::from($levelId);

        return ['id' => $level->value, 'name' => $level->label(), 'title' => __($level->title()), 'color' => $level->color()];
    }

    /**
     * The content of one step.
     *
     * @param  array<string, mixed>  $unit
     * @return array<string, mixed>
     */
    private function stepContent(User $user, array $unit, UnitStep $step): array
    {
        $id = (string) $unit['id'];

        return match ($step) {
            UnitStep::Vocab => ['vocab' => $unit['vocab']],
            UnitStep::Grammar => ['grammar' => $unit['grammar']],
            UnitStep::Dialogue => ['dialogue' => $unit['dialogue']],
            UnitStep::Practice => ['exercises' => $this->items($id, $unit['exercises'], '')],
            UnitStep::Reading => ['reading' => [
                'title' => $unit['reading']['title'],
                'text' => $unit['reading']['text'],
                'textRo' => $unit['reading']['textRo'],
                'questions' => $this->items($id, $unit['reading']['questions'], 'r'),
            ]],
            UnitStep::Test => ['test' => $this->test($user, $unit)],
        };
    }

    /**
     * The unit test: its questions, the lesson steps not done yet and where the result
     * card leads (the other units of the level).
     *
     * @param  array<string, mixed>  $unit
     * @return array<string, mixed>
     */
    private function test(User $user, array $unit): array
    {
        $done = $this->course->unitState($user, (string) $unit['id'])['steps'];

        return [
            'items' => $this->tests->build($unit),
            'size' => $this->tests->size(),
            'hasNew' => ($unit['testItems'] ?? []) !== [],
            'missing' => array_values(array_diff(UnitStep::lessonValues(), $done)),
            'units' => array_map(
                fn (array $outline): array => ['id' => $outline['id'], 'order' => $outline['order'], 'title' => $outline['title']],
                $this->content->level((string) $unit['levelId'])['units'] ?? [],
            ),
        ];
    }

    /**
     * The word bank row: how many extra words the unit has and how far the learner is with
     * them (null when the unit has no word bank).
     *
     * @return array{learned: int, learning: int, new: int, total: int}|null
     */
    private function bank(User $user, string $unitId): ?array
    {
        $words = $this->words->bankWords($unitId);

        return $words === [] ? null : $this->words->counts($user, $words);
    }

    /**
     * Exercises with their mistake keys ("<unit>:<n>", "<unit>:r<n>").
     *
     * @param  list<array<string, mixed>>  $exercises
     * @return list<array{ex: array<string, mixed>, unit: string, key: string}>
     */
    private function items(string $unitId, array $exercises, string $prefix): array
    {
        $items = [];

        foreach ($exercises as $index => $exercise) {
            $items[] = ['ex' => $exercise, 'unit' => $unitId, 'key' => "{$unitId}:{$prefix}{$index}"];
        }

        return $items;
    }
}
