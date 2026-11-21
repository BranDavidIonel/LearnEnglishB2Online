<?php

namespace App\Http\Requests\Api;

/** A word tapped in a lesson, as written, with the sentence it was tapped in (optional). */
class WordLookupRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'word' => ['required', 'string', 'max:100'],
            'sentence' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function word(): string
    {
        return $this->string('word')->toString();
    }

    public function sentence(): ?string
    {
        return $this->filled('sentence') ? $this->string('sentence')->toString() : null;
    }
}
