<?php

namespace App\Http\Requests\Api;

/**
 * The result of a paper of a Cambridge mock (exam, test and paper in the URL):
 * {score, correct, total, parts} for Reading / Listening, {score} for the others.
 */
class CambridgeResultRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric', 'between:0,1'],
            'correct' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'total' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'parts' => ['sometimes', 'array', 'max:20'],
            'parts.*' => ['integer', 'min:0', 'max:1000'],
        ];
    }

    /** @return array<string, mixed> */
    public function result(): array
    {
        $result = $this->safe()->only(['score', 'correct', 'total', 'parts']);
        $result['score'] = (float) $result['score'];

        return $result;
    }
}
