<?php

namespace App\Http\Requests\Api;

/** The answers of a level exam: [{unit, correct}] (the level is in the URL). */
class ExamRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $units = array_column($this->content()->level((string) $this->route('level'))['units'] ?? [], 'id');

        return [
            'results' => ['required', 'array', 'min:1', 'max:500'],
            'results.*' => ['required', 'array:unit,correct'],
            'results.*.unit' => ['required', 'string', $this->oneOf(array_flip($units))],
            'results.*.correct' => ['required', 'boolean'],
        ];
    }

    /** @return list<array{unit: string, correct: bool}> */
    public function results(): array
    {
        return array_values(array_map(
            fn (array $result): array => ['unit' => (string) $result['unit'], 'correct' => filter_var($result['correct'], FILTER_VALIDATE_BOOL)],
            (array) $this->input('results'),
        ));
    }
}
