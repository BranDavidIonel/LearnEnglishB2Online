<?php

namespace App\Http\Requests\Api;

/**
 * The result (0..1) of a conversation ("a1-s01", a scenario of the speaking content)
 * or of the role-play of a unit ("role-a1-u01").
 */
class ConversationRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scenario' => ['required', 'string', 'max:40', $this->oneOf($this->scenarios())],
            'score' => ['required', 'numeric', 'between:0,1'],
        ];
    }

    /** @return array<string, true> every scenario id and every "role-<unit>" */
    private function scenarios(): array
    {
        $ids = [];

        foreach ($this->content()->levels() as $level) {
            foreach ($this->content()->speaking($level['id'])['scenarios'] ?? [] as $scenario) {
                $ids[(string) ($scenario['id'] ?? '')] = true;
            }

            foreach ($level['units'] as $unit) {
                $ids['role-'.$unit['id']] = true;
            }
        }

        return $ids;
    }
}
