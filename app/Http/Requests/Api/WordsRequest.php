<?php

namespace App\Http\Requests\Api;

/**
 * One or more words of the course, by their English text (in the body: they may hold
 * spaces, apostrophes and question marks). With "grade" for a flashcard grade.
 */
class WordsRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'words' => ['required', 'array', 'min:1', 'max:5000'],
            'words.*' => ['required', 'string', 'distinct', $this->oneOf($this->content()->wordLevels())],
            'grade' => [$this->routeIs('api.words.grade') ? 'required' : 'prohibited', 'integer', 'in:0,1,2'],
        ];
    }

    /** @return list<string> */
    public function words(): array
    {
        return array_values(array_map('strval', (array) $this->input('words')));
    }
}
