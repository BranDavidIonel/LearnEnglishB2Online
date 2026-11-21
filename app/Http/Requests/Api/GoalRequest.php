<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

/** The daily XP goal: one of the choices of the settings page (20, 50, 100, 150). */
class GoalRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'goal' => ['required', 'integer', Rule::in(config('learning.rules.goals'))],
        ];
    }
}
