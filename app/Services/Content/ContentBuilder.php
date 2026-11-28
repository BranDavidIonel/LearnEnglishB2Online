<?php

namespace App\Services\Content;

/**
 * Builds every piece the ContentRepository keeps, from the files, following the original
 * server (loadContent, loadVerbs, loadCambridge) and the original store (load):
 * a unit file counts only with an id, a vocab list and an exercise list; its word bank
 * (bankNN.json) and test questions (testNN.json) live next to it; units are sorted by
 * their "order"; a test of a Cambridge exam counts only with an id, reading and listening.
 */
class ContentBuilder
{
    public function __construct(private readonly ContentFiles $files) {}

    /**
     * @param  list<string>  $levelIds
     * @param  list<string>  $examIds
     * @return array<string, mixed> piece name => value
     */
    public function build(array $levelIds, array $examIds): array
    {
        $pieces = ['levels' => []];
        $words = [];

        foreach ($levelIds as $index => $levelId) {
            $units = $this->units($levelId);
            $placement = $this->files->json("{$levelId}/placement.json");
            $outlines = [];

            foreach ($units as $unitIndex => $unit) {
                $unit['levelId'] = $levelId;
                $unit['index'] = $unitIndex;
                $pieces['unit:'.$unit['id']] = $unit;
                $outlines[] = $this->outline($unit);
                $words = $this->indexWords($words, $unit);
            }

            $pieces['placement:'.$levelId] = is_array($placement['questions'] ?? null) ? array_values($placement['questions']) : [];
            $pieces['speaking:'.$levelId] = $this->speaking($levelId);
            $pieces['levels'][] = [
                'id' => $levelId,
                'name' => strtoupper($levelId),
                'index' => $index,
                'placement' => count($pieces['placement:'.$levelId]),
                'units' => $outlines,
            ];
        }

        $pieces['words'] = $words;
        $pieces['wordLevels'] = array_map(fn (array $word): string => $word['levelId'], $words);
        $pieces['verbs'] = $this->verbs();
        $pieces['cambridge'] = $this->cambridge($examIds);
        $pieces['glossary'] = $this->glossary();
        $pieces['extraWords'] = $this->extraWords($pieces['glossary']['words'], $words);
        $pieces['help'] = $this->files->json('help.json');

        return $pieces;
    }

    /**
     * The glossary (glossary.json): the words of the lessons the course vocabulary does not
     * teach, and the names. A missing file is an empty glossary; an entry without a headword
     * is left out.
     *
     * @return array{words: list<array<string, mixed>>, names: list<array<string, mixed>>}
     */
    private function glossary(): array
    {
        $file = $this->files->json('glossary.json') ?? [];
        $glossary = ['words' => [], 'names' => []];

        foreach (array_keys($glossary) as $list) {
            foreach (is_array($file[$list] ?? null) ? $file[$list] : [] as $entry) {
                if (is_array($entry) && is_string($entry['en'] ?? null) && $entry['en'] !== '') {
                    $glossary[$list][] = $entry;
                }
            }
        }

        return $glossary;
    }

    /**
     * The glossary words that can be learned as extra words, by headword: the course
     * vocabulary wins when it has the same word (the glossary then only holds its forms).
     *
     * @param  list<array<string, mixed>>  $glossary
     * @param  array<string, array<string, mixed>>  $courseWords
     * @return array<string, array<string, mixed>>
     */
    private function extraWords(array $glossary, array $courseWords): array
    {
        $extra = [];

        foreach ($glossary as $entry) {
            $en = (string) $entry['en'];

            if (! isset($courseWords[$en]) && ! isset($extra[$en])) {
                $extra[$en] = $entry;
            }
        }

        return $extra;
    }

    /** @return list<array<string, mixed>> */
    private function units(string $levelId): array
    {
        $units = [];

        foreach ($this->files->list($levelId, '/^unit\d+\.json$/') as $file) {
            $unit = $this->files->json($file);

            if (! isset($unit['id']) || ! is_array($unit['vocab'] ?? null) || ! is_array($unit['exercises'] ?? null)) {
                continue;
            }

            $bank = $this->files->json(preg_replace('#/unit(\d+)#', '/bank$1', $file) ?? $file);
            $test = $this->files->json(preg_replace('#/unit(\d+)#', '/test$1', $file) ?? $file);
            $unit['bank'] = is_array($bank['words'] ?? null) ? array_values($bank['words']) : [];
            $unit['testItems'] = is_array($test['questions'] ?? null) ? array_values($test['questions']) : [];
            $units[] = $unit;
        }

        usort($units, fn (array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        return $units;
    }

    /**
     * What the rules need of a unit without its lessons.
     *
     * @param  array<string, mixed>  $unit
     * @return array<string, mixed>
     */
    private function outline(array $unit): array
    {
        return [
            'id' => $unit['id'],
            'order' => $unit['order'] ?? null,
            'title' => $unit['title'] ?? null,
            'titleRo' => $unit['titleRo'] ?? null,
            'emoji' => $unit['emoji'] ?? null,
            'levelId' => $unit['levelId'],
            'index' => $unit['index'],
            'vocab' => array_values(array_map(fn (array $word): string => (string) $word['en'], $unit['vocab'])),
            'bank' => array_values(array_map(fn (array $word): string => (string) $word['en'], $unit['bank'])),
            // How many exercises and test questions it has (enough to count "my mistakes").
            'exercises' => count($unit['exercises']),
            'testItems' => count($unit['testItems']),
        ];
    }

    /**
     * The first occurrence of a word wins; core words of a unit come before its bank.
     *
     * @param  array<string, array<string, mixed>>  $words
     * @param  array<string, mixed>  $unit
     * @return array<string, array<string, mixed>>
     */
    private function indexWords(array $words, array $unit): array
    {
        foreach (['vocab' => false, 'bank' => true] as $list => $isBank) {
            foreach ($unit[$list] as $word) {
                $en = (string) $word['en'];

                if (! isset($words[$en])) {
                    $words[$en] = [...$word, 'unit' => $unit['id'], 'levelId' => $unit['levelId'], ...($isBank ? ['bank' => true] : [])];
                }
            }
        }

        return $words;
    }

    /** @return array<string, mixed>|null */
    private function speaking(string $levelId): ?array
    {
        $speaking = $this->files->json("{$levelId}/speaking.json");

        foreach (['scenarios', 'phrases', 'questions'] as $list) {
            if (! is_array($speaking[$list] ?? null)) {
                return null;
            }
        }

        return $speaking;
    }

    /** @return list<array<string, mixed>> */
    private function verbs(): array
    {
        $verbs = $this->files->json('verbs.json')['verbs'] ?? [];
        $list = [];

        foreach (is_array($verbs) ? array_values($verbs) : [] as $i => $verb) {
            $list[] = [...$verb, 'rank' => $i + 1, 'levelId' => strtolower((string) ($verb['level'] ?? 'a1'))];
        }

        return $list;
    }

    /**
     * @param  list<string>  $examIds
     * @return list<array<string, mixed>>
     */
    private function cambridge(array $examIds): array
    {
        $exams = [];

        foreach ($examIds as $id) {
            $info = $this->files->json("cambridge/{$id}/exam.json");

            if ($info === null) {
                continue;
            }

            $tests = [];

            foreach ($this->files->list("cambridge/{$id}", '/^test\d+\.json$/') as $file) {
                $test = $this->files->json($file);

                if (isset($test['id'], $test['reading'], $test['listening'])) {
                    $tests[] = $test;
                }
            }

            $exams[] = [...$info, 'id' => $id, 'tests' => $tests];
        }

        return $exams;
    }
}
