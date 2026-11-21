<?php

namespace App\Http\Requests\Api;

/** A score from 0 to 1 (a unit test). */
class ScoreRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric', 'between:0,1'],
        ];
    }

    public function score(): float
    {
        return (float) $this->input('score');
    }
}
