<?php

namespace App\Services\Content;

use App\Enums\Level;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * The course content (resources/content), read exactly like the original server and
 * store did: the units of each level with their word bank and test questions, the
 * placement questions, the speaking practice, the irregular verbs and the Cambridge mocks.
 * Content keeps the keys of the JSON files (titleRo, exRo, testItems …), plus what the
 * original added while loading: levelId and index on units, rank and levelId on verbs,
 * unit / levelId / bank on the words of the word index.
 *
 * Everything is derived once and kept in the cache, piece by piece (the outline of the
 * course, one entry per unit, the word index …), under a key that carries a fingerprint
 * of the files: editing a content file is picked up by itself, and "cache:clear" (part of
 * "optimize:clear") empties it. Inside a request, pieces are also kept in memory.
 *
 * @phpstan-type UnitOutline array{id: string, order: int|null, title: string|null, titleRo: string|null, emoji: string|null, levelId: string, index: int, vocab: list<string>, bank: list<string>, exercises: int, testItems: int}
 * @phpstan-type LevelOutline array{id: string, name: string, index: int, placement: int, units: list<UnitOutline>}
 */
class ContentRepository
{
    /** The Cambridge exams, in the order the original listed them. */
    public const array CAMBRIDGE_EXAMS = ['pet', 'fce'];

    /** @var array<string, mixed> */
    private array $memory = [];

    private ?string $fingerprint = null;

    public function __construct(
        private readonly ContentFiles $files,
        private readonly Cache $cache,
        private readonly int $ttl,
    ) {}

    /**
     * The levels in course order, each with the outline of its units (no lesson content):
     * id, name ("A1"), index, units [{id, order, title, titleRo, emoji, levelId, index,
     * vocab: [en…], bank: [en…], exercises: count, testItems: count}], and how many
     * placement questions it has.
     *
     * @return list<LevelOutline>
     */
    public function levels(): array
    {
        /** @var list<LevelOutline> */
        return $this->piece('levels');
    }

    /** @return LevelOutline|null */
    public function level(string $id): ?array
    {
        foreach ($this->levels() as $level) {
            if ($level['id'] === $id) {
                return $level;
            }
        }

        return null;
    }

    /**
     * The outline of one unit (see levels()), or null when there is no such unit.
     *
     * @return UnitOutline|null
     */
    public function unitOutline(string $id): ?array
    {
        foreach ($this->levels() as $level) {
            foreach ($level['units'] as $unit) {
                if ($unit['id'] === $id) {
                    return $unit;
                }
            }
        }

        return null;
    }

    /**
     * A whole unit as in its JSON file, plus bank (its word bank), testItems (the new
     * questions of its test), levelId and index (its place in the level, from 0).
     *
     * @return array<string, mixed>|null
     */
    public function unit(string $id): ?array
    {
        if ($this->unitOutline($id) === null) {
            return null;
        }

        /** @var array<string, mixed> */
        return $this->piece('unit:'.$id);
    }

    /**
     * Every unit of a level, whole, in course order.
     *
     * @return list<array<string, mixed>>
     */
    public function units(string $levelId): array
    {
        $units = [];

        foreach ($this->level($levelId)['units'] ?? [] as $outline) {
            $unit = $this->unit((string) $outline['id']);

            if ($unit !== null) {
                $units[] = $unit;
            }
        }

        return $units;
    }

    /**
     * The placement questions of a level (empty when the level has none).
     *
     * @return list<array<string, mixed>>
     */
    public function placement(string $levelId): array
    {
        /** @var list<array<string, mixed>> */
        return $this->piece('placement:'.$levelId) ?? [];
    }

    /**
     * The speaking practice of a level ({level, scenarios, phrases, questions}), or null.
     *
     * @return array<string, mixed>|null
     */
    public function speaking(string $levelId): ?array
    {
        /** @var array<string, mixed>|null */
        return $this->piece('speaking:'.$levelId);
    }

    /**
     * Every word of the course by its English text. A word taught by several units keeps
     * its first occurrence (course order; in each unit its core words, then its bank).
     * Each word gets unit, levelId, and bank: true when it comes from a word bank.
     *
     * @return array<string, array<string, mixed>>
     */
    public function words(): array
    {
        /** @var array<string, array<string, mixed>> */
        return $this->piece('words');
    }

    /** @return array<string, mixed>|null */
    public function word(string $en): ?array
    {
        return $this->words()[$en] ?? null;
    }

    /**
     * The level of every word of the word index (en => levelId): a light version of
     * words() for counting and filtering.
     *
     * @return array<string, string>
     */
    public function wordLevels(): array
    {
        /** @var array<string, string> */
        return $this->piece('wordLevels');
    }

    /**
     * The irregular verbs, most used first, each with rank (1, 2 …) and levelId.
     *
     * @return list<array<string, mixed>>
     */
    public function verbs(): array
    {
        /** @var list<array<string, mixed>> */
        return $this->piece('verbs');
    }

    /** @return array<string, mixed>|null */
    public function verb(string $v1): ?array
    {
        foreach ($this->verbs() as $verb) {
            if ($verb['v1'] === $v1) {
                return $verb;
            }
        }

        return null;
    }

    /**
     * The glossary (glossary.json, keys of the file): "words" — the words shown in the lessons
     * that the course vocabulary does not teach (en, ro, pos, ipa, forms?) — and "names"
     * (en, ro, ipa?). Empty lists when the file is missing.
     *
     * @return array{words: list<array<string, mixed>>, names: list<array<string, mixed>>}
     */
    public function glossary(): array
    {
        /** @var array{words: list<array<string, mixed>>, names: list<array<string, mixed>>} */
        return $this->piece('glossary') ?? ['words' => [], 'names' => []];
    }

    /**
     * The glossary words that can be learned besides the course vocabulary, by headword
     * ("extra words": they go into the flashcards but never into the course numbers).
     * A glossary headword that is also a course word is the course word, so it is not here.
     *
     * @return array<string, array<string, mixed>>
     */
    public function extraWords(): array
    {
        /** @var array<string, array<string, mixed>> */
        return $this->piece('extraWords') ?? [];
    }

    /** @return array<string, mixed>|null */
    public function extraWord(string $en): ?array
    {
        return $this->extraWords()[$en] ?? null;
    }

    /**
     * The Cambridge mock exams: exam.json of each exam plus id and its tests (whole).
     *
     * @return list<array<string, mixed>>
     */
    public function cambridge(): array
    {
        /** @var list<array<string, mixed>> */
        return $this->piece('cambridge');
    }

    /** @return array<string, mixed>|null */
    public function cambridgeExam(string $id): ?array
    {
        foreach ($this->cambridge() as $exam) {
            if ($exam['id'] === $id) {
                return $exam;
            }
        }

        return null;
    }

    /** Forgets what was kept in memory (the cache entries stay). */
    public function refresh(): void
    {
        $this->memory = [];
        $this->fingerprint = null;
    }

    /** One derived piece: from memory, else from the cache, else built from the files. */
    private function piece(string $name): mixed
    {
        if (array_key_exists($name, $this->memory)) {
            return $this->memory[$name];
        }

        $this->fingerprint ??= $this->files->fingerprint();
        $cached = $this->cache->get($this->key($name));

        if (! is_array($cached) || ! array_key_exists('value', $cached)) {
            $this->build();

            return $this->memory[$name] ?? null;
        }

        return $this->memory[$name] = $cached['value'];
    }

    private function key(string $name): string
    {
        return "content:{$this->fingerprint}:{$name}";
    }

    /** Reads every file once and keeps every piece (the values are wrapped, so null is kept too). */
    private function build(): void
    {
        $pieces = (new ContentBuilder($this->files))->build(Level::values(), self::CAMBRIDGE_EXAMS);
        $entries = [];

        foreach ($pieces as $name => $value) {
            $entries[$this->key($name)] = ['value' => $value];
        }

        $this->cache->putMany($entries, $this->ttl);
        $this->memory = $pieces;
    }
}
