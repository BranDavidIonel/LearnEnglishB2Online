<?php

namespace App\Http\Requests\Api;

/** The result of a round of a game, kept when it beats the record (the game is in the URL). */
class RecordRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'value' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
