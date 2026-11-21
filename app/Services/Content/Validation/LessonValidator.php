<?php

namespace App\Services\Content\Validation;

/**
 * The course of every level (docs/content/SCHEMA.md): the ten units, their word banks and
 * unit tests, and the placement test.
 */
final class LessonValidator extends Validator
{
    public const array LEVELS = ['a1', 'a2', 'b1', 'b2'];

    public const int UNITS = 10;

    private const array POS = ['noun', 'verb', 'adj', 'adv', 'phrase', 'prep', 'pron', 'conj', 'other'];

    private const array WORD_FIELDS = ['en', 'ro', 'ipa', 'emoji', 'ex', 'exRo'];

    /** Expected length of the reading text, in words. */
    private const array READING_WORDS = ['A1' => [50, 110], 'A2' => [90, 160], 'B1' => [150, 240], 'B2' => [220, 330]];

    private const array MIN_EXERCISES = ['mc' => 6, 'fill' => 3, 'order' => 3, 'translate' => 2, 'listen' => 2, 'match' => 1];

    /**
     * Words of a unit in total (core vocabulary + word bank): 600 / 1200 / 2000 / 3000 words
     * by the end of A1 / A2 / B1 / B2.
     */
    private const array UNIT_WORDS = ['A1' => 60, 'A2' => 60, 'B1' => 80, 'B2' => 100];

    /** Half of every unit test is new questions, the other half comes from the unit's exercises. */
    private const int TEST_QUESTIONS = 12;

    private const array MIN_TEST_QUESTIONS = ['mc' => 4, 'fill' => 2, 'order' => 2, 'translate' => 1, 'listen' => 1];

    private readonly ExerciseValidator $exercises;

    private readonly UnitSentences $sentences;

    /**
     * Core words of the whole course (lower case) and the first unit that teaches each one.
     *
     * @var array<string, string>
     */
    private array $coreWords = [];

    /**
     * Word bank words met so far (lower case) and their bank.
     *
     * @var array<string, string>
     */
    private array $bankWords = [];

    public function __construct(string $root)
    {
        parent::__construct($root);
        $this->exercises = new ExerciseValidator;
        $this->sentences = new UnitSentences;
    }

    public function validate(ValidationReport $report): void
    {
        $this->coreWords = $this->collectCoreWords();
        $this->bankWords = [];

        foreach (self::LEVELS as $level) {
            $this->level($report, $level);
        }
    }

    private function level(ValidationReport $report, string $level): void
    {
        if (! is_dir($this->path($level))) {
            $report->error($level, 'folder missing');

            return;
        }

        // A core word may appear only once in a level.
        $levelWords = [];

        for ($n = 1; $n <= self::UNITS; $n++) {
            $file = "{$level}/unit".self::pad($n).'.json';

            if (! $this->exists($file)) {
                $report->error($file, 'file missing');

                continue;
            }

            $unit = $this->decode($file, $report);

            if ($unit === null) {
                continue;
            }

            $this->unit($report, $file, $unit, $level, $n, $levelWords);
            $this->bank($report, $unit, $level, $n);
            $this->test($report, $unit, $level, $n);
        }

        $this->placement($report, $level);
    }

    /**
     * @param  array<array-key, mixed>  $unit
     * @param  array<string, string>  $levelWords
     */
    private function unit(ValidationReport $report, string $file, array $unit, string $level, int $n, array &$levelWords): void
    {
        $upper = strtoupper($level);
        $id = "{$level}-u".self::pad($n);

        if (($unit['id'] ?? null) !== $id) {
            $report->error($file, "id must be \"{$id}\"");
        }

        if (($unit['level'] ?? null) !== $upper) {
            $report->error($file, "level must be \"{$upper}\"");
        }

        $order = $unit['order'] ?? null;

        if (! Value::integer($order) || (int) $order !== $n) {
            $report->error($file, "order must be {$n}");
        }

        foreach (['title', 'titleRo', 'emoji', 'goalRo'] as $key) {
            if (! Value::filled($unit[$key] ?? null)) {
                $report->error($file, "missing {$key}");
            }
        }

        $this->vocab($report, $file, $unit['vocab'] ?? null, $levelWords);
        $this->grammar($report, $file, $unit['grammar'] ?? null);
        $this->dialogue($report, $file, $unit['dialogue'] ?? null);
        $this->reading($report, $file, $unit['reading'] ?? null, $upper);
        $this->unitExercises($report, $file, $unit['exercises'] ?? null);

        if (array_key_exists('video', $unit)) {
            $this->video($report, $file, $unit['video']);
        }
    }

    /** @param  array<string, string>  $levelWords */
    private function vocab(ValidationReport $report, string $file, mixed $vocab, array &$levelWords): void
    {
        $count = Value::countLabel($vocab);

        if (! Value::isList($vocab) || count($vocab) < 14 || count($vocab) > 18) {
            $report->error($file, "vocab must have 14-18 items (has {$count})");
        }

        foreach (Value::items($vocab) as $i => $word) {
            $word = Value::fields($word);
            $this->word($report, $file, $word, "vocab[{$i}]");

            $key = self::key($word['en'] ?? null);

            if (isset($levelWords[$key])) {
                $report->error($file, 'vocab "'.Value::show($word['en'] ?? null)."\" already used in {$levelWords[$key]}");
            } else {
                $levelWords[$key] = $file;
            }
        }
    }

    /**
     * The fields a core word and a word bank word share.
     *
     * @param  array<array-key, mixed>  $word
     */
    private function word(ValidationReport $report, string $file, array $word, string $where): void
    {
        foreach (self::WORD_FIELDS as $key) {
            if (! Value::filled($word[$key] ?? null)) {
                $report->error($file, "{$where} missing {$key}");
            }
        }

        $pos = $word['pos'] ?? null;

        if (! in_array($pos, self::POS, true)) {
            $report->error($file, "{$where} bad pos \"".Value::show($pos).'"');
        }

        if (array_key_exists('wiki', $word) && ! Value::filled($word['wiki'])) {
            $report->error($file, "{$where} empty wiki");
        }
    }

    private function grammar(ValidationReport $report, string $file, mixed $grammar): void
    {
        $grammar = Value::fields($grammar);

        if (! Value::filled($grammar['title'] ?? null) || ! Value::filled($grammar['titleRo'] ?? null)) {
            $report->error($file, 'grammar needs title + titleRo');

            return;
        }

        $sections = $grammar['sections'] ?? null;

        if (! Value::isList($sections) || count($sections) < 2 || count($sections) > 5) {
            $report->error($file, 'grammar needs 2-5 sections');
        }

        foreach (Value::items($sections) as $i => $section) {
            $this->grammarSection($report, $file, Value::fields($section), "grammar.sections[{$i}]");
        }

        $tips = $grammar['tipsRo'] ?? null;

        if (! Value::isList($tips) || count($tips) < 2 || ! array_all($tips, fn ($tip) => Value::filled($tip))) {
            $report->error($file, 'grammar needs >=2 tipsRo');
        }
    }

    /** @param  array<array-key, mixed>  $section */
    private function grammarSection(ValidationReport $report, string $file, array $section, string $where): void
    {
        if (! Value::filled($section['headingRo'] ?? null) || ! Value::filled($section['textRo'] ?? null)) {
            $report->error($file, "{$where} needs headingRo + textRo");
        }

        $examples = $section['examples'] ?? null;

        if (! Value::isList($examples) || count($examples) < 2 || ! array_all($examples, fn ($example) => self::bilingual($example))) {
            $report->error($file, "{$where} needs >=2 examples {en, ro}");
        }

        if (! Value::truthy($section['table'] ?? null)) {
            return;
        }

        $table = Value::fields($section['table']);
        $headers = $table['headers'] ?? null;
        $rows = $table['rows'] ?? null;

        if (! Value::isList($headers) || ! Value::isList($rows) || ! array_all($rows, fn ($row) => Value::isList($row) && count($row) === count($headers))) {
            $report->error($file, "{$where} bad table");
        }
    }

    private function dialogue(ValidationReport $report, string $file, mixed $dialogue): void
    {
        $dialogue = Value::fields($dialogue);
        $lines = $dialogue['lines'] ?? null;

        if (! Value::filled($dialogue['titleRo'] ?? null) || ! Value::isList($lines) || count($lines) < 6 || count($lines) > 10) {
            $report->error($file, 'dialogue needs titleRo + 6-10 lines');

            return;
        }

        $lines = array_map(fn ($line) => Value::fields($line), $lines);

        if (! array_all($lines, fn ($line) => Value::filled($line['speaker'] ?? null) && Value::filled($line['en'] ?? null) && Value::filled($line['ro'] ?? null))) {
            $report->error($file, 'dialogue line needs speaker, en, ro');
        }

        if (Value::distinct(array_map(fn ($line) => $line['speaker'] ?? null, $lines)) !== 2) {
            $report->error($file, 'dialogue must have exactly 2 speakers');
        }
    }

    private function reading(ValidationReport $report, string $file, mixed $reading, string $level): void
    {
        $reading = Value::fields($reading);
        $text = $reading['text'] ?? null;

        if (! Value::filled($reading['title'] ?? null) || ! Value::filled($text) || ! Value::filled($reading['textRo'] ?? null)) {
            $report->error($file, 'reading needs title, text, textRo');

            return;
        }

        $words = Value::wordCount($text);
        [$min, $max] = self::READING_WORDS[$level];

        if ($words < $min || $words > $max) {
            $report->warning($file, "reading has {$words} words (expected {$min}-{$max})");
        }

        $questions = $reading['questions'] ?? null;

        if (! Value::isList($questions) || count($questions) !== 4) {
            $report->error($file, 'reading needs exactly 4 questions');

            return;
        }

        foreach ($questions as $i => $question) {
            $this->exercises->check($report, $file, $question, "reading.questions[{$i}]", onlyMc: true);
        }
    }

    private function unitExercises(ValidationReport $report, string $file, mixed $exercises): void
    {
        $count = Value::countLabel($exercises);

        if (! Value::isList($exercises) || count($exercises) < 18 || count($exercises) > 20) {
            $report->error($file, "exercises must have 18-20 items (has {$count})");

            return;
        }

        foreach ($exercises as $i => $exercise) {
            $this->exercises->check($report, $file, $exercise, "exercises[{$i}]");
        }

        $types = self::countTypes($exercises);

        foreach (self::MIN_EXERCISES as $type => $min) {
            $has = $types[$type] ?? 0;

            if ($has < $min) {
                $report->error($file, "needs at least {$min} \"{$type}\" exercises (has {$has})");
            }
        }

        $answers = array_map(
            fn ($exercise) => Value::fields($exercise)['answer'] ?? null,
            array_values(array_filter($exercises, fn ($exercise) => (Value::fields($exercise)['type'] ?? null) === 'mc')),
        );

        // The right answer should not always sit in the same one or two places.
        if (count($answers) >= 6 && Value::distinct($answers) < 3) {
            $report->warning($file, 'mc correct answers are not varied enough in position');
        }
    }

    private function video(ValidationReport $report, string $file, mixed $video): void
    {
        $fields = Value::fields($video);

        if (! Value::truthy($video)
            || preg_match('/^[\w-]{11}\z/', Value::text($fields['youtubeId'] ?? null)) !== 1
            || ! Value::filled($fields['title'] ?? null)) {
            $report->error($file, 'video needs an 11-char youtubeId + title');
        }
    }

    /** @param  array<array-key, mixed>  $unit */
    private function bank(ValidationReport $report, array $unit, string $level, int $n): void
    {
        $file = "{$level}/bank".self::pad($n).'.json';

        if (! $this->exists($file)) {
            $report->warning($file, 'file missing (the unit has no word bank)');

            return;
        }

        $bank = $this->decode($file, $report);

        if ($bank === null) {
            return;
        }

        $id = "{$level}-u".self::pad($n);

        if (($bank['unit'] ?? null) !== $id) {
            $report->error($file, "unit must be \"{$id}\"");
        }

        $words = $bank['words'] ?? null;

        if (! Value::isList($words) || $words === []) {
            $report->error($file, 'needs a words[] array');

            return;
        }

        $total = self::UNIT_WORDS[strtoupper($level)];
        $vocab = $unit['vocab'] ?? null;

        if (Value::isList($vocab) && count($words) !== $total - count($vocab)) {
            $expected = $total - count($vocab);
            $report->warning($file, 'has '.count($words)." words, expected {$expected} (unit total {$total})");
        }

        foreach ($words as $i => $word) {
            $this->bankWord($report, $file, Value::fields($word), "words[{$i}]");
        }
    }

    /** @param  array<array-key, mixed>  $word */
    private function bankWord(ValidationReport $report, string $file, array $word, string $where): void
    {
        $this->word($report, $file, $word, $where);

        $ipa = $word['ipa'] ?? null;

        if (Value::filled($ipa) && preg_match('/^\/.+\/\z/u', Value::trim($ipa)) !== 1) {
            $report->error($file, "{$where} ipa must look like /.../");
        }

        $en = $word['en'] ?? null;

        if (Value::filled($en) && $en !== Value::trim($en)) {
            $report->error($file, "{$where} en has extra spaces");
        }

        // A bank word is never a repeat of a word taught anywhere else in the course.
        $key = self::key($en);
        $shown = Value::show($en);

        if (isset($this->coreWords[$key])) {
            $report->error($file, "\"{$shown}\" is already a core word in {$this->coreWords[$key]}");
        } elseif (isset($this->bankWords[$key])) {
            $report->error($file, "\"{$shown}\" is already in {$this->bankWords[$key]}");
        } else {
            $this->bankWords[$key] = $file;
        }
    }

    /** @param  array<array-key, mixed>  $unit */
    private function test(ValidationReport $report, array $unit, string $level, int $n): void
    {
        $file = "{$level}/test".self::pad($n).'.json';

        if (! $this->exists($file)) {
            $report->warning($file, "file missing (the unit test uses only the unit's exercises)");

            return;
        }

        $test = $this->decode($file, $report);

        if ($test === null) {
            return;
        }

        $id = "{$level}-u".self::pad($n);

        if (($test['unit'] ?? null) !== $id) {
            $report->error($file, "unit must be \"{$id}\"");
        }

        $questions = $test['questions'] ?? null;

        if (! Value::isList($questions)) {
            $report->error($file, 'needs a questions[] array');

            return;
        }

        if (count($questions) !== self::TEST_QUESTIONS) {
            $report->error($file, 'needs exactly '.self::TEST_QUESTIONS.' questions (has '.count($questions).')');
        }

        $this->testQuestions($report, $file, $questions);
        $this->newSentences($report, $file, $questions, $this->sentences->ofUnit($unit));
    }

    /** @param  list<mixed>  $questions */
    private function testQuestions(ValidationReport $report, string $file, array $questions): void
    {
        $checked = [];

        foreach ($questions as $i => $question) {
            if ((Value::fields($question)['type'] ?? null) === 'match') {
                $report->error($file, "questions[{$i}]: \"match\" is not used in tests");

                continue;
            }

            $this->exercises->check($report, $file, $question, "questions[{$i}]");
            $checked[] = $question;
        }

        $types = self::countTypes($checked);

        foreach (self::MIN_TEST_QUESTIONS as $type => $min) {
            $has = $types[$type] ?? 0;

            if ($has < $min) {
                $report->error($file, "needs at least {$min} \"{$type}\" questions (has {$has})");
            }
        }
    }

    /**
     * A test checks understanding, not memory: no question may repeat a sentence of the unit
     * or another question of the same test.
     *
     * @param  list<mixed>  $questions
     * @param  array<string, true>  $known
     */
    private function newSentences(ValidationReport $report, string $file, array $questions, array $known): void
    {
        $mine = [];

        foreach ($questions as $i => $question) {
            foreach ($this->sentences->ofExercise($question) as $sentence) {
                $flat = $this->sentences->flatten($sentence);

                if ($flat === '') {
                    continue;
                }

                if (isset($known[$flat])) {
                    $report->error($file, "questions[{$i}]: \"{$flat}\" already appears in the unit — tests need new sentences");
                }

                if (isset($mine[$flat])) {
                    $report->error($file, "questions[{$i}]: \"{$flat}\" is repeated inside the test");
                }

                $mine[$flat] = true;
            }
        }
    }

    private function placement(ValidationReport $report, string $level): void
    {
        $file = "{$level}/placement.json";

        if (! $this->exists($file)) {
            $report->error($file, 'file missing');

            return;
        }

        $placement = $this->decode($file, $report);

        if ($placement === null) {
            return;
        }

        if (($placement['level'] ?? null) !== strtoupper($level)) {
            $report->error($file, 'bad level');
        }

        $questions = $placement['questions'] ?? null;

        if (! Value::isList($questions) || count($questions) !== 12) {
            $report->error($file, 'needs exactly 12 questions');

            return;
        }

        foreach ($questions as $i => $question) {
            $this->exercises->check($report, $file, $question, "questions[{$i}]", onlyMc: true);
            $options = Value::fields($question)['options'] ?? null;

            if (Value::truthy($options) && Value::length($options) !== 4) {
                $report->error($file, "questions[{$i}]: needs 4 options");
            }
        }
    }

    /**
     * Every core word of the course, read up front so a bank word of A1 is checked against
     * the core words of B2 as well.
     *
     * @return array<string, string>
     */
    private function collectCoreWords(): array
    {
        $words = [];

        foreach (self::LEVELS as $level) {
            for ($n = 1; $n <= self::UNITS; $n++) {
                $file = "{$level}/unit".self::pad($n).'.json';

                foreach (Value::items($this->peek($file)['vocab'] ?? null) as $word) {
                    $words[self::key(Value::fields($word)['en'] ?? null)] ??= $file;
                }
            }
        }

        return $words;
    }

    /**
     * @param  list<mixed>  $exercises
     * @return array<string, int>
     */
    private static function countTypes(array $exercises): array
    {
        $types = [];

        foreach ($exercises as $exercise) {
            $type = Value::show(Value::fields($exercise)['type'] ?? null);
            $types[$type] = ($types[$type] ?? 0) + 1;
        }

        return $types;
    }

    private static function bilingual(mixed $pair): bool
    {
        $pair = Value::fields($pair);

        return Value::filled($pair['en'] ?? null) && Value::filled($pair['ro'] ?? null);
    }

    /** Words are compared without case. */
    private static function key(mixed $word): string
    {
        return mb_strtolower(Value::show($word));
    }

    private static function pad(int $n): string
    {
        return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }
}
