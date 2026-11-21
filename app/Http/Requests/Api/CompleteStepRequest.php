<?php

namespace App\Http\Requests\Api;

use App\Enums\UnitStep;
use Illuminate\Validation\Rule;

/** A lesson step of a unit done (the unit is in the URL). */
class CompleteStepRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'step' => ['required', 'string', Rule::in(UnitStep::lessonValues())],
        ];
    }

    public function step(): UnitStep
    {
        return UnitStep::from($this->string('step')->value());
    }
}
