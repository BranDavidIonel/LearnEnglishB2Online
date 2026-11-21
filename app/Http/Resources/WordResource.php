<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A word of the course with the learner's state (types/progress.ts "WordWithState"):
 * the fields of the content (en, ro, pos, ipa, emoji, wiki, ex, exRo), where it comes
 * from (unit, levelId, bank) and its review state. Built from WordReviewService::withState().
 *
 * @property array<string, mixed> $resource
 */
class WordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $word = $this->resource;

        return [
            ...$word,
            'bank' => (bool) ($word['bank'] ?? false),
            'status' => $word['status'],
            'box' => $word['box'],
            'due' => $word['due'],
            'known' => $word['known'],
        ];
    }
}
