<?php

namespace App\Http\Requests\Api;

/** The text of a progress.json file (read by the page from the file the learner picked). */
class ImportProgressRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The original server accepted files up to 5 MB.
            'content' => ['required', 'string', 'max:5000000'],
        ];
    }
}
