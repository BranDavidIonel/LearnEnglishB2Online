<?php

namespace App\Http\Resources\Words;

use App\Http\Resources\WordResource;
use Illuminate\Http\Request;

/**
 * A word of a quick-sort batch (components/modules/words/types.ts "SortWord"): the word with
 * the learner's state, and the four meanings of its "what does it mean?" question, the right
 * one among them. Built by WordPages::batch().
 *
 * @property array<string, mixed> $resource
 */
class SortWordResource extends WordResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'options' => $this->resource['options'],
        ];
    }
}
