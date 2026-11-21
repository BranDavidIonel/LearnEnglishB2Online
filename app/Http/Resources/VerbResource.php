<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An irregular verb with the learner's state (types/progress.ts "VerbWithState"): the
 * fields of verbs.json (v1, v2, v3, level, ro, ipa, ex2, ex2Ro, ex3, ex3Ro), rank, levelId
 * and its review state. Built from VerbReviewService::withState().
 *
 * @property array<string, mixed> $resource
 */
class VerbResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $verb = $this->resource;

        return [
            ...$verb,
            'status' => $verb['status'],
            'box' => $verb['box'],
            'due' => $verb['due'],
            'known' => $verb['known'],
        ];
    }
}
