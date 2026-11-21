<?php

namespace App\Http\Requests\Api;

/** The draft of a writing task of a Cambridge mock (exam and test in the URL). */
class CambridgeDraftRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'task' => ['required', 'integer', 'min:0', 'max:20'],
            'text' => ['present', 'nullable', 'string', 'max:20000'],
        ];
    }
}
