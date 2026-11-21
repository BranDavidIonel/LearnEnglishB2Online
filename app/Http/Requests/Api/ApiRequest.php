<?php

namespace App\Http\Requests\Api;

use App\Services\Content\ContentRepository;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** The requests of the progress API: what they share. */
abstract class ApiRequest extends FormRequest
{
    /**
     * A value that must be one of the keys of a set (a word of the course, a verb …),
     * checked with a lookup instead of an "in:" rule with thousands of values.
     *
     * @param  array<int|string, mixed>  $set
     */
    protected function oneOf(array $set): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($set): void {
            if (! is_string($value) || ! array_key_exists($value, $set)) {
                $fail('validation.in')->translate();
            }
        };
    }

    protected function content(): ContentRepository
    {
        return app(ContentRepository::class);
    }
}
