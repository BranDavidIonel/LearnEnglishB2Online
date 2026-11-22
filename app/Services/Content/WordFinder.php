<?php

namespace App\Services\Content;

use App\Enums\WordRelation;
use App\Enums\WordSource;

/**
 * Finds what a word form of a lesson is ("alerts", "went", "children's", "Iași") so the
 * word window can explain it. In this order:
 *
 * 1. the form as it is (case kept, then lower case, then ignoring case): a course word, an
 *    irregular-verb form, a glossary headword or one of its "forms", a name;
 * 2. the possessive "'s" taken off, and the rest looked up through every step;
 * 3. a course word (or irregular verb) reached by the derivation rules, then a glossary word
 *    reached by them: -s / -es / -ies (plural of a noun, third person of a verb), -ed / -d / -ied
 *    (past of a regular verb), -ing (of a verb), -er / -est / -ier / -iest (of an adjective or
 *    adverb), with a dropped "e" or a doubled consonant where English does it;
 * 4. the plural -s of a name ("CRMs").
 *
 * A rule only counts when its result is a real headword whose part of speech takes that
 * ending: "her" is not "he" + -r, "as" is not the plural of "a", "seed" is not the past of
 * "see" (an irregular verb has no regular past), "thing" is not "th" + -ing.
 *
 * It also finds the expressions (of the course and the glossary) of a sentence that hold the
 * tapped word, comparing headwords ("I was on call" holds "be on call"). Words are split
 * exactly as the browser splits them: see words().
 */
class WordFinder
{
    /**
     * A word: a run of letters in which single apostrophes or hyphens may stand between two
     * letters; a run that touches a digit or a "___" gap is not a word ("12th", "B2", "wait___ed").
     */
    private const string WORD = "/(?<![\\p{L}\\p{M}\\p{N}_])(?>[\\p{L}\\p{M}]+(?:['’-][\\p{L}\\p{M}]+)*)(?![\\p{L}\\p{M}\\p{N}_])/u";

    private const string VOWELS = 'aeiouy';

    /** "he's", "what's" … are "he is", not a possessive. */
    private const array IS_CONTRACTED = ['he', 'she', 'it', 'that', 'there', 'here', 'what', 'where', 'who', 'how', 'when', 'why', 'let'];

    /** Present forms the irregular-verb list does not hold, for comparing expressions. */
    private const array PRESENT_FORMS = ['am' => 'be', 'is' => 'be', 'are' => 'be', 'has' => 'have'];

    /** Contracted endings: what they stand for in an expression, and every headword they can be. */
    private const array CONTRACTIONS = [
        "n't" => ['not'],
        "'m" => ['am', 'be'],
        "'re" => ['are', 'be'],
        "'s" => ['is', 'has', 'be', 'have', "'s"],
        "'ve" => ['have'],
        "'ll" => ['will'],
        "'d" => ['would', 'had', 'have'],
    ];

    /** Contractions whose first part is not written as the word itself. */
    private const array NEGATIONS = ["can't" => 'can', "won't" => 'will', "shan't" => 'shall'];

    /** An expression with one of these words stands for words we cannot match reliably. */
    private const array PLACEHOLDERS = ['someone', "someone's", 'somebody', 'something', 'sb', 'sth', 'one', "one's", 'oneself'];

    /** "make up your mind" is also "make up my mind", "his mind" … */
    private const array POSSESSIVES = ['my', 'your', 'his', 'her', 'its', 'our', 'their'];

    private bool $loaded = false;

    /** @var array<string, array<string, mixed>> course words without a space, by text */
    private array $course = [];

    /** @var array<string, string> lower-case text => text of the course word */
    private array $courseLower = [];

    /** @var array<string, array<string, mixed>> irregular verbs by base form (lower case) */
    private array $verbs = [];

    /** @var array<string, list<array{verb: string, relation: WordRelation|null}>> any form of an irregular verb (lower case) */
    private array $verbForms = [];

    /** @var array<string, array<string, mixed>> glossary words by headword */
    private array $glossary = [];

    /** @var array<string, string> lower-case headword => headword */
    private array $glossaryLower = [];

    /** @var array<string, string> a form listed by a glossary word (lower case) => its headword */
    private array $glossaryForms = [];

    /** @var array<string, array<string, mixed>> names by text */
    private array $names = [];

    /** @var array<string, string> lower-case name => name */
    private array $namesLower = [];

    /** @var list<array{en: string, ro: string, words: list<string>}>|null */
    private ?array $expressions = null;

    public function __construct(private readonly ContentRepository $content) {}

    /**
     * The words of a text, as written (curly apostrophes made straight).
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        preg_match_all(self::WORD, $text, $matches);

        return array_map(fn (string $word): string => str_replace('’', "'", $word), $matches[0]);
    }

    /**
     * The words of a text with whether each one starts a sentence (it is the first word, or
     * follows a sentence end, quotes and brackets between them allowed).
     *
     * @return list<array{word: string, start: bool}>
     */
    public static function occurrences(string $text): array
    {
        preg_match_all(self::WORD, $text, $matches, PREG_OFFSET_CAPTURE);
        $words = [];

        foreach ($matches[0] as [$word, $offset]) {
            $before = (string) preg_replace('/[\s"“”‘’\'()\[\]*_—–-]+$/u', '', substr($text, 0, $offset));
            $words[] = ['word' => str_replace('’', "'", $word), 'start' => $before === '' || preg_match('/[.!?…]$/u', $before) === 1];
        }

        return $words;
    }

    /** Whether a tapped form starts a sentence of the text it was tapped in (somewhere in it). */
    public static function startsSentence(string $sentence, string $form): bool
    {
        $word = self::words($form)[0] ?? '';

        return array_any(self::occurrences($sentence), fn (array $item): bool => $item['start'] && $item['word'] === $word);
    }

    /**
     * What a tapped form is, or null when nothing explains it. A word that starts a sentence
     * may be capitalised only for that, so it is looked up in lower case first ("May I…?" is
     * the modal, "in May" the month); elsewhere as written first.
     */
    public function find(string $form, bool $sentenceStart = false): ?WordMatch
    {
        $word = self::words($form)[0] ?? '';

        if ($word === '') {
            return null;
        }

        $this->load();

        return $this->asWritten($word, $sentenceStart)
            ?? $this->possessive($word, $sentenceStart)
            ?? $this->derived(mb_strtolower($word))
            ?? $this->namePlural($word);
    }

    /**
     * The expressions of a sentence that hold the tapped form ("I was on call" and
     * "was" → "be on call"), course expressions first, in course order.
     *
     * @return list<array{en: string, ro: string}>
     */
    public function expressions(string $sentence, string $form): array
    {
        $this->load();
        $tapped = mb_strtolower(self::words($form)[0] ?? '');
        $parts = $this->parts(self::words($sentence));

        if ($tapped === '' || $parts === []) {
            return [];
        }

        $found = [];

        foreach ($this->expressionList() as $expression) {
            if ($this->occursWith($expression['words'], $parts, $tapped)) {
                $found[$expression['en']] ??= ['en' => $expression['en'], 'ro' => $expression['ro']];
            }
        }

        return array_values($found);
    }

    // ---------- step 1: the form as it is ----------

    private function asWritten(string $word, bool $sentenceStart): ?WordMatch
    {
        $lower = mb_strtolower($word);

        foreach (array_unique($sentenceStart ? [$lower, $word] : [$word, $lower]) as $text) {
            $match = $this->exactly($text);

            if ($match !== null) {
                return $match;
            }
        }

        return $this->ignoringCase($lower, $word !== $lower);
    }

    private function exactly(string $text): ?WordMatch
    {
        if (isset($this->course[$text])) {
            return $this->courseWord($this->course[$text]);
        }

        if (isset($this->verbForms[$text])) {
            $forms = $this->verbForms[$text];

            return $this->verbWord($forms[0]['verb'], $forms[0]['relation']);
        }

        if (isset($this->glossary[$text])) {
            return $this->glossaryWord($this->glossary[$text]);
        }

        if (isset($this->glossaryForms[$text])) {
            return $this->glossaryForm($this->glossaryForms[$text], $text);
        }

        return isset($this->names[$text]) ? $this->name($this->names[$text]) : null;
    }

    /**
     * Any spelling of a word written with capitals ("WI-FI", "Thank You"); a word written in
     * lower case is not a word that is always capitalised ("may" is not the month "May").
     * Names are matched ignoring case either way ("paul's").
     */
    private function ignoringCase(string $lower, bool $capitals): ?WordMatch
    {
        if ($capitals && isset($this->courseLower[$lower])) {
            return $this->courseWord($this->course[$this->courseLower[$lower]]);
        }

        if ($capitals && isset($this->glossaryLower[$lower])) {
            return $this->glossaryWord($this->glossary[$this->glossaryLower[$lower]]);
        }

        return isset($this->namesLower[$lower]) ? $this->name($this->names[$this->namesLower[$lower]]) : null;
    }

    // ---------- step 2: the possessive ----------

    /** "children's" → children → child; "he's" is "he is", so its headword comes back as a plain form. */
    private function possessive(string $word, bool $sentenceStart): ?WordMatch
    {
        if (mb_strlen($word) < 3 || ! str_ends_with(mb_strtolower($word), "'s")) {
            return null;
        }

        $rest = mb_substr($word, 0, -2);
        $match = $this->asWritten($rest, $sentenceStart) ?? $this->derived(mb_strtolower($rest)) ?? $this->namePlural($rest);

        if ($match === null) {
            return null;
        }

        return $match->as(in_array(mb_strtolower($rest), self::IS_CONTRACTED, true) ? WordRelation::Form : WordRelation::Possessive);
    }

    // ---------- step 3: the derivation rules ----------

    private function derived(string $lower): ?WordMatch
    {
        $candidates = $this->candidates($lower);

        foreach ([false, true] as $glossary) {
            foreach ($candidates as [$base, $kind]) {
                $match = $this->headwordFor($base, $kind, $lower, $glossary);

                if ($match !== null) {
                    return $match;
                }
            }
        }

        return null;
    }

    /**
     * Every (base, kind of ending) a form could come from, most likely first. The kinds:
     * "s" (plural or third person), "ed" (past), "ing", "er" (comparative), "est" (superlative).
     *
     * @return list<array{string, string}>
     */
    private function candidates(string $word): array
    {
        $candidates = [];
        $ends = fn (string $suffix): bool => str_ends_with($word, $suffix) && mb_strlen($word) > mb_strlen($suffix);
        $stem = fn (string $suffix): string => mb_substr($word, 0, -mb_strlen($suffix));

        if ($ends('ies')) {
            $candidates[] = [$stem('ies').'y', 's'];
        }

        if ($ends('es') && preg_match('/(s|x|z|ch|sh|o)es$/u', $word) === 1) {
            $candidates[] = [$stem('es'), 's'];
        }

        if ($ends('s') && ! str_ends_with($word, 'ss') && ! str_ends_with($word, "'s")) {
            $candidates[] = [$stem('s'), 's'];
        }

        foreach (['ed' => 'ed', 'ing' => 'ing', 'er' => 'er', 'est' => 'est'] as $suffix => $kind) {
            if ($ends($suffix)) {
                array_push($candidates, ...$this->stems($stem($suffix), $kind));
            }
        }

        if ($ends('ied')) {
            $candidates[] = [$stem('ied').'y', 'ed'];
        }

        if ($ends('ying')) {
            $candidates[] = [$stem('ying').'ie', 'ing'];
        }

        foreach (['ier' => 'er', 'iest' => 'est'] as $suffix => $kind) {
            if ($ends($suffix)) {
                $candidates[] = [$stem($suffix).'y', $kind];
            }
        }

        return array_values(array_filter($candidates, fn (array $candidate): bool => $this->wordLike(...$candidate)));
    }

    /**
     * The bases of a stem left by -ed, -ing, -er or -est: the stem itself ("worked"), the stem
     * with its "e" back ("liked", "making", "nicer"), the stem with a doubled consonant
     * undone ("stopped", "running", "bigger"). A stem of one short syllable ending in a
     * consonant is never the base itself, because English doubles that consonant: "riding" is
     * ride (rid gives "ridding"), "hoping" hope, "safer" safe.
     *
     * @return list<array{string, string}>
     */
    private function stems(string $stem, string $kind): array
    {
        $vowel = '['.self::VOWELS.']';
        $short = preg_match('/^(?:[^'.self::VOWELS.']*|[^'.self::VOWELS.']*qu)'.$vowel.'[^'.self::VOWELS.'wx]$/u', $stem) === 1;
        $bases = $short ? [[$stem.'e', $kind]] : [[$stem, $kind], [$stem.'e', $kind]];
        $length = mb_strlen($stem);

        // English doubles the last consonant after one short vowel: stop, run, travel, quit (not "ear").
        if ($length >= 4 && preg_match('/(?:[^'.self::VOWELS.']|qu)['.self::VOWELS.']([^'.self::VOWELS.'wx])\1$/u', $stem) === 1) {
            $bases[] = [mb_substr($stem, 0, -1), $kind];
        }

        return $bases;
    }

    /**
     * A base worth looking up: two letters or more, with a vowel unless it takes -s or -ed
     * (abbreviations: "TVs", "CCed"); "thing" is not "th" + -ing.
     */
    private function wordLike(string $base, string $kind): bool
    {
        return mb_strlen($base) >= 2 && (in_array($kind, ['s', 'ed'], true) || preg_match('/['.self::VOWELS.']/u', $base) === 1);
    }

    /** The headword a base names when its part of speech takes the ending, on one side (course or glossary). */
    private function headwordFor(string $base, string $kind, string $form, bool $glossary): ?WordMatch
    {
        $entries = $glossary ? $this->glossaryEntries($base) : $this->courseEntries($base);
        $fitting = array_values(array_filter($entries, fn (array $entry): bool => $this->takes($entry, $kind, $form)));

        if ($fitting === []) {
            return null;
        }

        [$entry, $kindOfEntry] = $fitting[0];
        $match = match ($kindOfEntry) {
            'course' => $this->courseWord($entry),
            'verb' => $this->verbWord((string) $entry['v1'], null),
            default => $this->glossaryWord($entry),
        };

        return $match->as($this->relation($kind, array_map(fn (array $item): string => $this->pos($item), $fitting)));
    }

    /**
     * Whether a headword takes an ending:
     * - -s: a noun or a verb, an adjective used as a noun ("professionals"), a number or
     *   quantifier of three letters or more ("hundreds", "lots"; "his" is not "hi" + -s);
     * - -ed: a regular verb (an irregular one has its own past), -ing: any verb;
     * - -ed and -ing also a noun or an adjective used as a verb ("changed", "coding",
     *   "cleaned"), when the form really holds it: an "e" put back must follow a consonant
     *   ("feed" is not "fee" + -d);
     * - -er / -est: an adjective, an adverb of four letters or more ("sooner", not "outer"),
     *   a quantifier of three letters or more ("fewer").
     *
     * @param  array{array<string, mixed>, string}  $item
     */
    private function takes(array $item, string $kind, string $form): bool
    {
        $pos = $this->pos($item);
        $base = mb_strtolower($item[1] === 'verb' ? (string) $item[0]['v1'] : (string) $item[0]['en']);
        $long = mb_strlen($base) >= 3;
        $holds = str_starts_with($form, $base) || preg_match('/[^'.self::VOWELS.']e$/u', $base) === 1;

        return $base !== $form && match ($kind) {
            's' => in_array($pos, ['noun', 'verb', 'adj'], true) || ($pos === 'other' && $long),
            'ed' => ($pos === 'verb' && ! isset($this->verbs[$base])) || (in_array($pos, ['noun', 'adj'], true) && $holds),
            'ing' => $pos === 'verb' || (in_array($pos, ['noun', 'adj'], true) && $holds),
            default => $pos === 'adj' || ($pos === 'adv' && mb_strlen($base) >= 4) || ($pos === 'other' && $long),
        };
    }

    /** @param  list<string>  $parts  the parts of speech of the headwords that fit */
    private function relation(string $kind, array $parts): WordRelation
    {
        return match ($kind) {
            's' => $this->plural($parts),
            'ed' => WordRelation::Past,
            'ing' => WordRelation::Ing,
            'er' => WordRelation::Comparative,
            default => WordRelation::Superlative,
        };
    }

    /**
     * -s is a plural, or the third person of a verb; "works" of a headword that is both is just a form.
     *
     * @param  list<string>  $parts
     */
    private function plural(array $parts): WordRelation
    {
        $verbs = count(array_filter($parts, fn (string $pos): bool => $pos === 'verb'));

        return match ($verbs) {
            0 => WordRelation::Plural,
            count($parts) => WordRelation::Third,
            default => WordRelation::Form,
        };
    }

    /** @param  array{array<string, mixed>, string}  $item */
    private function pos(array $item): string
    {
        return $item[1] === 'verb' ? 'verb' : (string) ($item[0]['pos'] ?? '');
    }

    /**
     * The course words and irregular verbs a lower-case base can be.
     *
     * @return list<array{array<string, mixed>, string}>
     */
    private function courseEntries(string $base): array
    {
        $entries = [];

        if (isset($this->courseLower[$base])) {
            $entries[] = [$this->course[$this->courseLower[$base]], 'course'];
        }

        if (isset($this->verbs[$base])) {
            $entries[] = [$this->verbs[$base], 'verb'];
        }

        return $entries;
    }

    /** @return list<array{array<string, mixed>, string}> */
    private function glossaryEntries(string $base): array
    {
        return isset($this->glossaryLower[$base]) ? [[$this->glossary[$this->glossaryLower[$base]], 'glossary']] : [];
    }

    // ---------- step 4: names ----------

    /** "CRMs", "ERPs": a name takes a plain plural -s, nothing else. */
    private function namePlural(string $word): ?WordMatch
    {
        $lower = mb_strtolower($word);

        if (! str_ends_with($lower, 's') || str_ends_with($lower, "'s")) {
            return null;
        }

        $name = $this->namesLower[mb_substr($lower, 0, -1)] ?? null;

        return $name === null ? null : $this->name($this->names[$name])->as(WordRelation::Plural);
    }

    // ---------- what a match is made of ----------

    /** @param  array<string, mixed>  $word */
    private function courseWord(array $word): WordMatch
    {
        $en = (string) $word['en'];

        return new WordMatch($en, WordSource::Course, null, $word, true, $this->verbs[mb_strtolower($en)] ?? null);
    }

    /** An irregular verb: the course word with its text when there is one, else the verb itself. */
    private function verbWord(string $v1, ?WordRelation $relation): WordMatch
    {
        $verb = $this->verbs[mb_strtolower($v1)];
        $word = $this->course[(string) $verb['v1']] ?? null;

        if ($word !== null) {
            return $this->courseWord($word)->as($relation);
        }

        $entry = [
            'en' => (string) $verb['v1'],
            'ro' => (string) ($verb['ro'] ?? ''),
            'pos' => 'verb',
            // "/biː/ – /wɒz, wɜː/ – /biːn/": the first one is the base form.
            'ipa' => trim(explode('–', (string) ($verb['ipa'] ?? ''))[0]) ?: null,
            'levelId' => $verb['levelId'] ?? null,
        ];

        return new WordMatch((string) $verb['v1'], WordSource::Course, $relation, $entry, false, $verb);
    }

    /** @param  array<string, mixed>  $entry */
    private function glossaryWord(array $entry): WordMatch
    {
        $en = (string) $entry['en'];

        // The course explains its own words; the glossary only maps their odd forms.
        if (isset($this->course[$en])) {
            return $this->courseWord($this->course[$en]);
        }

        return new WordMatch($en, WordSource::Glossary, null, $entry, true, $this->verbs[mb_strtolower($en)] ?? null);
    }

    /**
     * A form a glossary word lists. The list does not say which form it is, so it is told from
     * the headword and the ending: "children's" possessive, "children" plural, "lying" -ing,
     * "panicked" past, "worst" superlative; anything else just a form ("worse").
     */
    private function glossaryForm(string $headword, string $form): WordMatch
    {
        $match = $this->glossaryWord($this->glossary[$headword]);
        $pos = (string) ($this->glossary[$headword]['pos'] ?? '');

        return $match->as(match (true) {
            str_ends_with($form, "'s") => WordRelation::Possessive,
            $pos === 'noun' => WordRelation::Plural,
            $pos === 'verb' && str_ends_with($form, 'ing') => WordRelation::Ing,
            $pos === 'verb' && str_ends_with($form, 'ed') => WordRelation::Past,
            $pos === 'adj' && str_ends_with($form, 'st') => WordRelation::Superlative,
            $pos === 'adj' && str_ends_with($form, 'er') => WordRelation::Comparative,
            default => WordRelation::Form,
        });
    }

    /** @param  array<string, mixed>  $entry */
    private function name(array $entry): WordMatch
    {
        return new WordMatch((string) $entry['en'], WordSource::Name, null, $entry, false);
    }

    // ---------- expressions ----------

    /**
     * The words of a sentence split into the parts an expression is compared with ("I'm" →
     * "I" + "'m"), each with every headword it can be and the word it was written in.
     *
     * @param  list<string>  $words
     * @return list<array{heads: array<string, true>, word: string}>
     */
    private function parts(array $words): array
    {
        $parts = [];

        foreach ($words as $word) {
            $lower = mb_strtolower($word);

            foreach ($this->split($lower) as $i => $piece) {
                $heads = $i > 0 ? self::CONTRACTIONS[$piece] : $this->heads($piece);
                $parts[] = ['heads' => array_fill_keys($heads, true), 'word' => $lower];
            }
        }

        return $parts;
    }

    /**
     * A lower-case word and its contracted ending, apart ("don't" → do + n't, "won't" → will + n't).
     *
     * @return list<string>
     */
    private function split(string $lower): array
    {
        if (isset(self::NEGATIONS[$lower])) {
            return [self::NEGATIONS[$lower], "n't"];
        }

        foreach (array_keys(self::CONTRACTIONS) as $ending) {
            if (str_ends_with($lower, $ending) && mb_strlen($lower) > mb_strlen($ending)) {
                return [mb_substr($lower, 0, -mb_strlen($ending)), $ending];
            }
        }

        return [$lower];
    }

    /**
     * Every headword a lower-case word can be: itself, its irregular verb, the present forms
     * of be / have, a glossary headword listing it, the bases the rules reach.
     *
     * @return list<string>
     */
    private function heads(string $lower): array
    {
        $heads = [$lower];

        foreach ($this->verbForms[$lower] ?? [] as $form) {
            $heads[] = mb_strtolower($form['verb']);
        }

        if (isset(self::PRESENT_FORMS[$lower])) {
            $heads[] = self::PRESENT_FORMS[$lower];
        }

        if (isset($this->glossaryForms[$lower])) {
            $heads[] = mb_strtolower($this->glossaryForms[$lower]);
        }

        foreach ($this->candidates($lower) as [$base, $kind]) {
            foreach ([...$this->courseEntries($base), ...$this->glossaryEntries($base)] as $item) {
                if ($this->takes($item, $kind, $lower)) {
                    $heads[] = $base;
                }
            }
        }

        return array_values(array_unique($heads));
    }

    /**
     * Whether an expression occurs, word after word, in the parts of a sentence, covering
     * the tapped word.
     *
     * @param  list<string>  $words
     * @param  list<array{heads: array<string, true>, word: string}>  $parts
     */
    private function occursWith(array $words, array $parts, string $tapped): bool
    {
        $length = count($words);

        for ($start = 0; $start + $length <= count($parts); $start++) {
            $span = array_slice($parts, $start, $length);

            if ($this->fits($words, $span) && in_array($tapped, array_column($span, 'word'), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $words
     * @param  list<array{heads: array<string, true>, word: string}>  $span
     */
    private function fits(array $words, array $span): bool
    {
        foreach ($words as $i => $word) {
            $heads = $span[$i]['heads'];
            $fits = $word === 'your'
                ? array_any(self::POSSESSIVES, fn (string $possessive): bool => isset($heads[$possessive]))
                : isset($heads[$word]);

            if (! $fits) {
                return false;
            }
        }

        return true;
    }

    /**
     * The expressions that can be compared word by word — the course's, then the glossary's
     * ("sat nav"), each once: two words or more, without a slash, brackets, dots or a word that
     * stands for something else ("talk someone into"). Their contracted endings are written
     * out ("I'd like" → i would like).
     *
     * @return list<array{en: string, ro: string, words: list<string>}>
     */
    private function expressionList(): array
    {
        if ($this->expressions !== null) {
            return $this->expressions;
        }

        /** @var array<string, array{en: string, ro: string, words: list<string>}> $expressions by text, each once */
        $expressions = [];

        foreach ([...array_values($this->content->words()), ...$this->content->glossary()['words']] as $word) {
            $en = (string) $word['en'];

            if (isset($expressions[$en]) || ! str_contains($en, ' ') || preg_match('/[\/()…]|\.\.\./u', $en) === 1) {
                continue;
            }

            $words = [];

            foreach (self::words($en) as $piece) {
                foreach ($this->split(mb_strtolower($piece)) as $i => $part) {
                    $words[] = $i > 0 ? self::CONTRACTIONS[$part][0] : $part;
                }
            }

            if (count($words) >= 2 && array_intersect($words, self::PLACEHOLDERS) === []) {
                $expressions[$en] = ['en' => $en, 'ro' => (string) ($word['ro'] ?? ''), 'words' => $words];
            }
        }

        return $this->expressions = array_values($expressions);
    }

    // ---------- the indexes ----------

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        foreach ($this->content->words() as $word) {
            $en = (string) $word['en'];

            if (! str_contains($en, ' ')) {
                $this->course[$en] = $word;
                $this->courseLower[mb_strtolower($en)] ??= $en;
            }
        }

        $this->loadVerbs();
        $this->loadGlossary();
    }

    private function loadVerbs(): void
    {
        foreach ($this->content->verbs() as $verb) {
            $v1 = (string) $verb['v1'];
            $this->verbs[mb_strtolower($v1)] ??= $verb;

            foreach (['v1' => null, 'v2' => WordRelation::Past, 'v3' => WordRelation::Participle] as $key => $relation) {
                foreach (explode('/', (string) ($verb[$key] ?? '')) as $form) {
                    $form = mb_strtolower(trim($form));

                    // "had" is both the past and the participle: the past is said first.
                    if ($form !== '' && ! in_array($v1, array_column($this->verbForms[$form] ?? [], 'verb'), true)) {
                        $this->verbForms[$form][] = ['verb' => $v1, 'relation' => $relation];
                    }
                }
            }
        }
    }

    private function loadGlossary(): void
    {
        $glossary = $this->content->glossary();

        foreach ($glossary['words'] as $entry) {
            $en = (string) $entry['en'];
            $this->glossary[$en] ??= $entry;
            $this->glossaryLower[mb_strtolower($en)] ??= $en;

            foreach (is_array($entry['forms'] ?? null) ? $entry['forms'] : [] as $form) {
                $this->glossaryForms[mb_strtolower((string) $form)] ??= $en;
            }
        }

        foreach ($glossary['names'] as $entry) {
            $en = (string) $entry['en'];
            $this->names[$en] ??= $entry;
            $this->namesLower[mb_strtolower($en)] ??= $en;
        }
    }
}
