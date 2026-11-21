<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

/** Sentences said well enough: how many, and the XP each is worth (2, or 3 for an interview answer). */
class SpokenRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'count' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'xp' => ['sometimes', 'integer', Rule::in(config('learning.xp.spoken'))],
        ];
    }
}
