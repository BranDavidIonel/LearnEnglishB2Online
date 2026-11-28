<?php

namespace App\Services\Help;

use App\Enums\Level;
use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\WordReviewService;

/**
 * What the help page shows, from help.json (docs/content/SCHEMA-HELP.md): the pronunciation
 * signs with their example words, the daily plan with the learner's distance to the B1 and
 * B2 vocabulary, and the advice for outside the app. The file only names an example word:
 * its transcription and meaning come from the word's own entry, so they are written once.
 *
 * @phpstan-type Example array{en: string, ipa: string, ro: string}
 * @phpstan-type Sound array{symbol: string, likeRo: string, textRo: string|null, examples: list<Example>}
 * @phpstan-type Group array{id: string, titleRo: string, noteRo: string|null, sounds: list<Sound>}
 * @phpstan-type Goal array{level: array{id: string, name: string, color: string}, vocab: array{learned: int, learning: int, new: int, total: int}, days: int}
 */
class HelpPage
{
    /** The levels the plan measures the vocabulary against: the two Cambridge exams of the course. */
    private const array GOALS = ['b1', 'b2'];

    public function __construct(
        private readonly ContentRepository $content,
        private readonly WordReviewService $words,
    ) {}

    /**
     * The pronunciation guide: the signs by group, each with its example words.
     *
     * @return array{introRo: string, groups: list<Group>}
     */
    public function sounds(): array
    {
        $guide = $this->part('sounds');
        $groups = [];

        foreach ($this->rows($guide['groups'] ?? null) as $group) {
            $sounds = [];

            foreach ($this->rows($group['sounds'] ?? null) as $sound) {
                $sounds[] = [
                    'symbol' => $this->text($sound['symbol'] ?? null),
                    'likeRo' => $this->text($sound['likeRo'] ?? null),
                    'textRo' => isset($sound['textRo']) ? $this->text($sound['textRo']) : null,
                    'examples' => $this->examples($sound['examples'] ?? null),
                ];
            }

            $groups[] = [
                'id' => $this->text($group['id'] ?? null),
                'titleRo' => $this->text($group['titleRo'] ?? null),
                'noteRo' => isset($group['noteRo']) ? $this->text($group['noteRo']) : null,
                'sounds' => $sounds,
            ];
        }

        return ['introRo' => $this->text($guide['introRo'] ?? null), 'groups' => $groups];
    }

    /**
     * The daily plan of the file, plus the words handed out per day ("batch") and, for B1
     * and B2, the learner's vocabulary so far and the days it takes to meet the words not
     * started yet at one batch a day.
     *
     * @return array<string, mixed>
     */
    public function plan(User $user): array
    {
        $batch = max(1, (int) config('learning.rules.word_batch'));
        $goals = [];

        foreach (self::GOALS as $levelId) {
            if ($this->content->level($levelId) === null) {
                continue;
            }

            $level = Level::from($levelId);
            $vocab = $this->words->vocabTo($user, $levelId);
            $goals[] = [
                'level' => ['id' => $level->value, 'name' => $level->label(), 'color' => $level->color()],
                'vocab' => $vocab,
                'days' => (int) ceil($vocab['new'] / $batch),
            ];
        }

        return [...$this->part('plan'), 'batch' => $batch, 'goals' => $goals];
    }

    /**
     * What to do besides the app.
     *
     * @return list<array<string, mixed>>
     */
    public function tips(): array
    {
        return $this->rows(($this->content->help() ?? [])['tips'] ?? null);
    }

    /**
     * The words named as examples, as the course (or the glossary) writes them. A name that
     * is not a word of the course is left out; the tests check that there is none.
     *
     * @return list<Example>
     */
    private function examples(mixed $names): array
    {
        $examples = [];

        foreach (is_array($names) ? $names : [] as $en) {
            $word = is_string($en) ? ($this->content->word($en) ?? $this->content->extraWord($en)) : null;

            if ($word !== null) {
                $examples[] = [
                    'en' => $this->text($word['en'] ?? null),
                    'ipa' => $this->text($word['ipa'] ?? null),
                    'ro' => $this->text($word['ro'] ?? null),
                ];
            }
        }

        return $examples;
    }

    /** @return array<string, mixed> */
    private function part(string $name): array
    {
        $part = ($this->content->help() ?? [])[$name] ?? null;

        /** @var array<string, mixed> */
        return is_array($part) ? $part : [];
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $value): array
    {
        /** @var list<array<string, mixed>> */
        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
