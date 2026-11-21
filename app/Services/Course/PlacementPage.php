<?php

namespace App\Services\Course;

use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\PlacementService;

/**
 * What the placement test page shows: every level (the result may land on any of them),
 * the questions of the levels that have some, in course order, and the last result.
 */
class PlacementPage
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly PlacementService $placement,
        private readonly UnitPage $units,
    ) {}

    /** @return array<string, mixed> */
    public function data(User $user): array
    {
        $levels = [];
        $parts = [];

        foreach ($this->content->levels() as $level) {
            $levels[] = [...$this->units->level($level['id']), 'firstUnit' => $level['units'][0]['id'] ?? null];
            $questions = $this->content->placement($level['id']);

            if ($questions !== []) {
                $parts[] = ['level' => $level['id'], 'items' => array_map(fn (array $ex): array => ['ex' => $ex], $questions)];
            }
        }

        return [
            'levels' => $levels,
            'parts' => $parts,
            'last' => $this->placement->placement($user),
            'passMark' => (float) config('learning.rules.placement_pass'),
        ];
    }
}
