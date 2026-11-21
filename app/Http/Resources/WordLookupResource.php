<?php

namespace App\Http\Resources;

use App\Enums\WordSource;
use App\Services\Content\WordMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the word window shows for a tapped word (types/progress.ts "WordLookup"): the headword
 * and how the form relates to it, the entry that explains it, the learner's state when it can
 * be learned, its irregular verb and the course expressions of the sentence that hold it.
 * Built from WordLookupService::lookup().
 *
 * @property array{word: string, match: WordMatch|null, state: array<string, mixed>|null, phrases: list<array{en: string, ro: string}>} $resource
 */
class WordLookupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $match = $this->resource['match'];
        $word = $this->resource['word'];

        return [
            'word' => $word,
            'found' => $match !== null,
            'source' => $match?->source->value,
            'base' => $match?->headword,
            // The same word written another way ("Hello" for "hello") is not another form.
            'relation' => $match === null || mb_strtolower($match->headword) === mb_strtolower($word) ? null : $match->relation?->value,
            'entry' => $match === null ? null : $this->entry($match),
            'state' => $this->resource['state'],
            'verb' => $match?->verb === null ? null : [
                'v1' => (string) $match->verb['v1'],
                'v2' => (string) $match->verb['v2'],
                'v3' => (string) $match->verb['v3'],
                'ipa' => (string) ($match->verb['ipa'] ?? ''),
                'ro' => (string) ($match->verb['ro'] ?? ''),
            ],
            'phrases' => $this->resource['phrases'],
        ];
    }

    /** @return array<string, mixed> */
    private function entry(WordMatch $match): array
    {
        $entry = $match->entry;
        $course = $match->source === WordSource::Course;

        return [
            'en' => (string) $entry['en'],
            'ro' => (string) ($entry['ro'] ?? ''),
            'ipa' => $this->text($entry['ipa'] ?? null),
            'pos' => $this->text($entry['pos'] ?? null),
            'ex' => $this->text($entry['ex'] ?? null),
            'exRo' => $this->text($entry['exRo'] ?? null),
            'emoji' => $this->text($entry['emoji'] ?? null),
            'wiki' => $this->text($entry['wiki'] ?? null),
            'levelId' => $course ? $this->text($entry['levelId'] ?? null) : null,
            'unit' => $course ? $this->text($entry['unit'] ?? null) : null,
        ];
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
