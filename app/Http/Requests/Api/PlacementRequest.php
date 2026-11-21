<?php

namespace App\Http\Requests\Api;

use App\Enums\Level;

/** The placement test: the score (0..1) of each level tested, {a1: 0.83, a2: 0.5}. */
class PlacementRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scores' => ['required', 'array:'.implode(',', Level::values()), 'min:1'],
            'scores.*' => ['required', 'numeric', 'between:0,1'],
        ];
    }

    /** @return array<string, float> */
    public function scores(): array
    {
        return array_map('floatval', (array) $this->input('scores'));
    }
}
