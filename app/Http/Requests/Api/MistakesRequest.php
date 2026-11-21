<?php

namespace App\Http\Requests\Api;

/**
 * Questions to add to "my mistakes" and questions to take off it. A key is
 * "<unit>:<exercise>", "<unit>:t<test question>" or "<unit>:r<reading question>".
 */
class MistakesRequest extends ApiRequest
{
    private const string KEY = '/^[a-z][0-9]-u[0-9]{2}:[rt]?[0-9]{1,3}$/';

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'add' => ['required_without:clear', 'array', 'max:500'],
            'add.*' => ['string', 'regex:'.self::KEY],
            'clear' => ['required_without:add', 'array', 'max:500'],
            'clear.*' => ['string', 'max:40'],
        ];
    }

    /** @return list<string> */
    public function mistakeKeys(string $list): array
    {
        return array_values(array_map('strval', (array) $this->input($list, [])));
    }
}
