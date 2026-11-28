<?php

namespace App\Http\Resources\Words;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of "My words" (components/modules/words/types.ts "WordListRow"): only what the row,
 * the filters and the counts need, because the page gets all ~4,600 words at once (box and
 * known only for the words in the deck). The rest
 * of each word comes later, for its details (WordPages::details()). Built from
 * WordReviewService::withState().
 *
 * @property array<string, mixed> $resource
 */
class WordRowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $word = $this->resource;

        return [
            'en' => $word['en'],
            'ro' => $word['ro'],
            'ipa' => $word['ipa'],
            'emoji' => $word['emoji'],
            'levelId' => $word['levelId'],
            'status' => $word['status'],
            // Most words are not in the deck: their empty box and "known" are left out.
            'box' => $this->when($word['box'] !== null, $word['box']),
            'known' => $this->when((bool) $word['known'], true),
        ];
    }
}
