<?php

namespace App\Http\Resources\Verbs;

use App\Http\Resources\VerbResource;
use App\Services\Progress\VerbReviewService;
use App\Services\Verbs\VerbsPage;
use Illuminate\Http\Request;

/**
 * A verb of the irregular verbs page (types: VerbItem): the verb with its review state
 * (VerbResource), plus the shape of its forms, the alternatives of the past and the
 * participle ("learnt / learned") and every answer accepted when they are typed.
 */
class VerbItemResource extends VerbResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $verb = $this->resource;

        return [
            ...parent::toArray($request),
            'pattern' => VerbsPage::pattern($verb),
            'alts2' => VerbReviewService::alternatives((string) $verb['v2']),
            'alts3' => VerbReviewService::alternatives((string) $verb['v3']),
            'answers2' => VerbReviewService::answers((string) $verb['v2']),
            'answers3' => VerbReviewService::answers((string) $verb['v3']),
        ];
    }
}
