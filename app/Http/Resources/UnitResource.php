<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A unit with the learner's state (types/progress.ts "UnitSummary"). Built from
 * CourseProgressService::unitSummary().
 *
 * @property array<string, mixed> $resource
 */
class UnitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $unit = $this->resource;

        return [
            'id' => $unit['id'],
            'order' => $unit['order'],
            'title' => $unit['title'],
            'titleRo' => $unit['titleRo'],
            'emoji' => $unit['emoji'],
            'levelId' => $unit['levelId'],
            'index' => $unit['index'],
            'unlocked' => $unit['unlocked'],
            'progress' => $unit['progress'],
            'passed' => $unit['passed'],
            'review' => $unit['review'],
            'best' => $unit['best'],
            'tries' => $unit['tries'],
            'steps' => $unit['steps'],
        ];
    }
}
