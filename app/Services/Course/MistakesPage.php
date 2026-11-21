<?php

namespace App\Services\Course;

use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\MistakeService;

/**
 * What "My mistakes" shows: the questions to practise again and, for the list "where you
 * make the most mistakes", how each of their units is named.
 */
class MistakesPage
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly MistakeService $mistakes,
    ) {}

    /** @return array<string, mixed> */
    public function data(User $user): array
    {
        $items = $this->mistakes->items($user);
        $units = [];

        foreach (array_unique(array_column($items, 'unit')) as $unitId) {
            /** @var array<string, mixed> $unit */
            $unit = $this->content->unit($unitId);
            $units[$unitId] = [
                'emoji' => $unit['emoji'],
                'level' => $unit['level'],
                'order' => $unit['order'],
                'grammarTitle' => $unit['grammar']['titleRo'],
            ];
        }

        return ['items' => $items, 'units' => (object) $units];
    }
}
