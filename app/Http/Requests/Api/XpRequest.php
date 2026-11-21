<?php

namespace App\Http\Requests\Api;

/**
 * XP of an activity whose amount the page works out (a game, a sort batch, a verb drill,
 * the answers of a practice): 0 to config('learning.xp.activity_max').
 */
class XpRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:0', 'max:'.(int) config('learning.xp.activity_max')],
        ];
    }
}
