<?php

namespace App\Http\Requests\Api;

use App\Enums\Theme;
use Illuminate\Validation\Rule;

/** Some of the settings: only the keys sent change. */
class SettingsRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'theme' => ['sometimes', 'string', Rule::enum(Theme::class)],
            'rate' => ['sometimes', 'numeric', 'between:'.config('learning.rules.rate.min').','.config('learning.rules.rate.max')],
            'voice' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sound' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array{theme?: string, rate?: float, voice?: string|null, sound?: bool} */
    public function patch(): array
    {
        $patch = $this->safe()->only(['theme', 'rate', 'voice', 'sound']);

        if (array_key_exists('rate', $patch)) {
            $patch['rate'] = (float) $patch['rate'];
        }

        if (array_key_exists('sound', $patch)) {
            $patch['sound'] = $this->boolean('sound');
        }

        return $patch;
    }
}
