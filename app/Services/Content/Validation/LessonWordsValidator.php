<?php

namespace App\Services\Content\Validation;

use App\Services\Content\ContentFiles;
use App\Services\Content\ContentRepository;
use App\Services\Content\WordFinder;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

/**
 * Every word of a text where the word window is turned on (TappableTexts) can be tapped, so
 * every one must be explained: by the course vocabulary, an irregular verb, the glossary or
 * its names, through WordFinder exactly as the window does (a word that starts a sentence is
 * looked up in lower case first). A word explained nowhere is a warning on each file that
 * shows it, so new content cannot quietly bring a word the window cannot explain.
 *
 * @phpstan-type Unexplained array{form: string, count: int, source: string, files: list<string>, examples: list<array{en: string, ro: string}>}
 */
final class LessonWordsValidator extends Validator
{
    /** Sentences kept as examples of an unexplained word. */
    private const int EXAMPLES = 2;

    public function validate(ValidationReport $report): void
    {
        $finder = new WordFinder(new ContentRepository(new ContentFiles($this->root), new Repository(new ArrayStore), 0));

        foreach ($this->unexplained($finder, $report) as $word) {
            foreach ($word['files'] as $file) {
                $report->warning($file, "word \"{$word['form']}\" is explained nowhere (course, irregular verbs, glossary, names)");
            }
        }
    }

    /**
     * The words nothing explains (compared in lower case), in the order they first appear:
     * how often they appear, the source and files they appear in, and up to two sentences
     * that show them (with the course's translation when the sentence has one).
     *
     * @return list<Unexplained>
     */
    public function unexplained(WordFinder $finder, ?ValidationReport $report = null): array
    {
        $missing = [];
        $found = [];

        foreach ((new TappableTexts($this->root))->all() as $text) {
            $report?->checked($text['file']);

            foreach (WordFinder::occurrences($text['en']) as ['word' => $word, 'start' => $start]) {
                $found[$word][(int) $start] ??= $finder->find($word, $start) !== null;

                if (! $found[$word][(int) $start]) {
                    $missing = $this->note($missing, $word, $start, $text);
                }
            }
        }

        return array_values($missing);
    }

    /**
     * @param  array<string, Unexplained>  $missing
     * @param  array{file: string, source: string, en: string, ro: string}  $text
     * @return array<string, Unexplained>
     */
    private function note(array $missing, string $word, bool $start, array $text): array
    {
        $key = mb_strtolower($word);
        $entry = $missing[$key] ?? ['form' => $start ? $key : $word, 'count' => 0, 'source' => $text['source'], 'files' => [], 'examples' => []];
        $entry['count']++;

        if (! in_array($text['file'], $entry['files'], true)) {
            $entry['files'][] = $text['file'];
        }

        if (count($entry['examples']) < self::EXAMPLES) {
            $example = $this->example($text, $word);

            if (! in_array($example, $entry['examples'], true)) {
                $entry['examples'][] = $example;
            }
        }

        $missing[$key] = $entry;

        return $missing;
    }

    /**
     * The sentence of a text that shows a word; a text of several sentences has no translation
     * of that one sentence.
     *
     * @param  array{en: string, ro: string}  $text
     * @return array{en: string, ro: string}
     */
    private function example(array $text, string $word): array
    {
        $sentences = (array) preg_split('/(?<=[.!?])\s+|\s*\n\s*/u', Value::trim(str_replace('**', '', $text['en'])));

        if (count($sentences) === 1) {
            return ['en' => (string) $sentences[0], 'ro' => $text['ro']];
        }

        foreach ($sentences as $sentence) {
            if (in_array($word, WordFinder::words((string) $sentence), true)) {
                return ['en' => (string) $sentence, 'ro' => ''];
            }
        }

        return ['en' => (string) $sentences[0], 'ro' => ''];
    }
}
