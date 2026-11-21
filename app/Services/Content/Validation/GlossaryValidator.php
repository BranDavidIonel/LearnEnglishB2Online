<?php

namespace App\Services\Content\Validation;

use App\Services\Content\ContentFiles;
use App\Services\Content\ContentRepository;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

/**
 * The glossary (glossary.json, docs/content/SCHEMA.md): the words of the lessons the course
 * vocabulary does not teach, and the names. Every entry complete, each headword once, each
 * listed form claimed by one headword only, IPA between slashes, Romanian written with ș / ț.
 * A headword the course vocabulary already explains is a warning, unless the entry is there
 * only for its forms ("child": children).
 */
final class GlossaryValidator extends Validator
{
    public const string FILE = 'glossary.json';

    public function validate(ValidationReport $report): void
    {
        if (! $this->exists(self::FILE)) {
            $report->warning(self::FILE, 'file missing (the word window explains only the course vocabulary)');

            return;
        }

        $data = $this->decode(self::FILE, $report);

        if ($data === null) {
            return;
        }

        $words = $data['words'] ?? null;
        $names = $data['names'] ?? [];

        if (! Value::isList($words) || ! Value::isList($names)) {
            $report->error(self::FILE, 'needs a words[] array (and names[], when there are names)');

            return;
        }

        $this->words($report, $words);
        $this->names($report, $names);
    }

    /** @param  list<mixed>  $words */
    private function words(ValidationReport $report, array $words): void
    {
        $course = $this->courseWords();
        $seen = [];
        $forms = [];

        foreach ($words as $i => $word) {
            $word = Value::fields($word);
            $en = Value::show($word['en'] ?? null);
            $where = "words[{$i}] ({$en})";

            $this->entry($report, $where, $word, true);

            if (isset($seen[$en])) {
                $report->error(self::FILE, "{$where} repeated");
            }

            $seen[$en] = true;

            if (isset($course[$en]) && ! array_key_exists('forms', $word)) {
                $report->warning(self::FILE, "{$where} is already a word of the course ({$course[$en]})");
            }

            foreach ($this->forms($report, $where, $word) as $form) {
                $key = mb_strtolower($form);

                if (isset($forms[$key])) {
                    $report->error(self::FILE, "{$where} form \"{$form}\" is already a form of \"{$forms[$key]}\"");
                }

                $forms[$key] ??= $en;
            }
        }
    }

    /** @param  list<mixed>  $names */
    private function names(ValidationReport $report, array $names): void
    {
        $seen = [];

        foreach ($names as $i => $name) {
            $name = Value::fields($name);
            $en = Value::show($name['en'] ?? null);
            $where = "names[{$i}] ({$en})";

            $this->entry($report, $where, $name, false);

            if (isset($seen[$en])) {
                $report->error(self::FILE, "{$where} repeated");
            }

            $seen[$en] = true;
        }
    }

    /**
     * What a word and a name share: en and ro filled, ipa between slashes (a name may have
     * none), Romanian with comma-below ș / ț; a word also needs its part of speech.
     *
     * @param  array<array-key, mixed>  $entry
     */
    private function entry(ValidationReport $report, string $where, array $entry, bool $word): void
    {
        foreach (['en', 'ro', ...($word ? ['ipa'] : [])] as $key) {
            if (! Value::filled($entry[$key] ?? null)) {
                $report->error(self::FILE, "{$where} missing {$key}");
            }
        }

        if ($word && ! in_array($entry['pos'] ?? null, LessonValidator::POS, true)) {
            $report->error(self::FILE, "{$where} bad pos \"".Value::show($entry['pos'] ?? null).'"');
        }

        $ipa = $entry['ipa'] ?? null;

        if (! $word && array_key_exists('ipa', $entry) && ! Value::filled($ipa)) {
            $report->error(self::FILE, "{$where} empty ipa");
        } elseif (Value::filled($ipa) && preg_match('/^\/[^\/]+\/(, ?\/[^\/]+\/)*\z/u', Value::trim($ipa)) !== 1) {
            // A word with a weak and a strong form has both: "/ə/, /eɪ/".
            $report->error(self::FILE, "{$where} ipa must look like /…/ (or /…/, /…/)");
        }

        if (preg_match('/[şţŞŢ]/u', Value::show($entry['ro'] ?? null)) === 1) {
            $report->error(self::FILE, "{$where} uses ş/ţ with cedilla — use ș/ț");
        }
    }

    /**
     * The forms a word lists, when it lists them well.
     *
     * @param  array<array-key, mixed>  $word
     * @return list<string>
     */
    private function forms(ValidationReport $report, string $where, array $word): array
    {
        if (! array_key_exists('forms', $word)) {
            return [];
        }

        $forms = $word['forms'];

        if (! Value::isList($forms) || $forms === [] || ! array_all($forms, Value::filled(...))) {
            $report->error(self::FILE, "{$where} forms must be a list of words");

            return [];
        }

        /** @var list<string> $forms */
        return $forms;
    }

    /**
     * The single words of the course vocabulary, with the unit that teaches them.
     *
     * @return array<string, string>
     */
    private function courseWords(): array
    {
        $content = new ContentRepository(new ContentFiles($this->root), new Repository(new ArrayStore), 0);
        $words = [];

        foreach ($content->words() as $en => $word) {
            if (! str_contains((string) $en, ' ')) {
                $words[(string) $en] = (string) $word['unit'];
            }
        }

        return $words;
    }
}
