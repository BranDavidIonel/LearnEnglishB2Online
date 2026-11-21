<?php

namespace App\Http\Requests\Api;

/** One or more irregular verbs, by their base form. With "grade" (0 or 2) for a drill. */
class VerbsRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $verbs = array_flip(array_map('strval', array_column($this->content()->verbs(), 'v1')));

        return [
            'verbs' => ['required', 'array', 'min:1', 'max:500'],
            'verbs.*' => ['required', 'string', 'distinct', $this->oneOf($verbs)],
            'grade' => [$this->routeIs('api.verbs.grade') ? 'required' : 'prohibited', 'integer', 'in:0,2'],
        ];
    }

    /** @return list<string> */
    public function verbs(): array
    {
        return array_values(array_map('strval', (array) $this->input('verbs')));
    }
}
