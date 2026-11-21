<?php

namespace App\Http\Resources\Home;

use App\Http\Resources\LevelResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A level of the learning path (CourseMapService::learnLevels()): the level with its
 * state, its unit cards, its words learned, whether its open units still have words to
 * learn, and how many of its units wait for review.
 *
 * @property array<string, mixed> $resource
 */
class LearnLevelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $level = $this->resource;

        return [
            'level' => new LevelResource($level['summary']),
            'units' => CourseUnitResource::collection($level['units']),
            'words' => $level['words'],
            'canLearnWords' => $level['canLearnWords'],
            'review' => $level['review'],
        ];
    }
}
