<?php

namespace App\Http\Resources;

use App\Enums\Level;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A level with the learner's state (types/progress.ts "LevelSummary"). Built from
 * CourseProgressService::levelSummary(); title and description in the interface language.
 *
 * @property array<string, mixed> $resource
 */
class LevelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $level = $this->resource;
        $info = Level::from($level['id']);

        return [
            'id' => $level['id'],
            'name' => $level['name'],
            'title' => __($info->title()),
            'description' => __($info->description()),
            'color' => $info->color(),
            'index' => $level['index'],
            'units' => $level['units'],
            'unlocked' => $level['unlocked'],
            'done' => $level['done'],
            'passed' => $level['passed'],
            'skipped' => $level['skipped'],
            'progress' => $level['progress'],
            'exam' => $level['exam'],
        ];
    }
}
