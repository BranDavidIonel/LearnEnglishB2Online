<?php

namespace App\Services\Content;

use App\Enums\WordRelation;
use App\Enums\WordSource;

/**
 * What WordFinder found for a word form: the headword it belongs to, where its meaning comes
 * from, how the form relates to it, the content entry (a word of the word index, a glossary
 * word or name, or an irregular verb written as a word) and the irregular verb when the
 * headword is one. Only course words and glossary words can be learned.
 */
final readonly class WordMatch
{
    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>|null  $verb
     */
    public function __construct(
        public string $headword,
        public WordSource $source,
        public ?WordRelation $relation,
        public array $entry,
        public bool $learnable,
        public ?array $verb = null,
    ) {}

    public function as(?WordRelation $relation): self
    {
        return new self($this->headword, $this->source, $relation, $this->entry, $this->learnable, $this->verb);
    }
}
