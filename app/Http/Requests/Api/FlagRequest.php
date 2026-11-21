<?php

namespace App\Http\Requests\Api;

use App\Services\Progress\SettingsService;
use Illuminate\Validation\Rule;

/** An on/off flag of a page, e.g. {flag: "keepSkippedWords", value: true}. */
class FlagRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'flag' => ['required', 'string', Rule::in(SettingsService::FLAGS)],
            'value' => ['required', 'boolean'],
        ];
    }
}
