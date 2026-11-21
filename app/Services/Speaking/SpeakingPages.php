<?php

namespace App\Services\Speaking;

use App\Enums\WordStatus;
use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\CourseProgressService;
use App\Services\Progress\SpeakingService;
use App\Services\Progress\WordReviewService;

/**
 * What the speaking pages show (the original views/speaking.js): the level whose content is
 * practised, its conversations, phrase topics and questions, the lines of the open lesson
 * dialogues, and the words of the voice sprint. The learner's results come from SpeakingService.
 *
 * @phpstan-import-type LevelOutline from ContentRepository
 */
class SpeakingPages
{
    /** A sentence longer than this is too long to say from its translation. */
    private const int SAY_MAX_WORDS = 12;

    /** The voice sprint fills up with the words the learner has met when fewer are known. */
    private const int SPRINT_MIN_WORDS = 20;

    private const int SPRINT_KNOWN_MIN = 12;

    public function __construct(
        private readonly ContentRepository $content,
        private readonly CourseProgressService $course,
        private readonly WordReviewService $words,
        private readonly SpeakingService $speaking,
    ) {}

    /**
     * The level asked for while it is open, else the level being worked on.
     *
     * @return LevelOutline
     */
    public function chosenLevel(User $user, ?string $levelId): array
    {
        $level = $levelId === null ? null : $this->content->level($levelId);

        if ($level !== null && $this->course->levelUnlocked($user, $level['id'])) {
            return $level;
        }

        /** @var LevelOutline */
        return $this->course->currentLevel($user);
    }

    /**
     * The ids of the open levels that have units (the levels the picker lets the learner choose).
     *
     * @return list<string>
     */
    public function openLevels(User $user): array
    {
        $ids = [];

        foreach ($this->content->levels() as $level) {
            if ($level['units'] !== [] && $this->course->levelUnlocked($user, $level['id'])) {
                $ids[] = $level['id'];
            }
        }

        return $ids;
    }

    /**
     * The hub: every level of the picker with what its cards show, and the learner's totals.
     *
     * @return array{levels: list<array<string, mixed>>, current: string, stats: array{said: int, talks: int, best: array<string, int>}}
     */
    public function hub(User $user): array
    {
        $state = $this->speaking->state($user);
        $levels = [];

        foreach ($this->content->levels() as $level) {
            if ($level['units'] === []) {
                continue;
            }

            $unlocked = $this->course->levelUnlocked($user, $level['id']);
            $scenarios = $this->scenarios($level['id']);

            $levels[] = [
                'id' => $level['id'],
                'unlocked' => $unlocked,
                'scenarios' => $scenarios === null ? null : count($scenarios),
                'talked' => count(array_filter($scenarios ?? [], fn (array $s): bool => isset($state['scen'][$s['id']]))),
                'units' => $unlocked ? $this->roleUnits($user, $level, $state['scen']) : [],
            ];
        }

        return [
            'levels' => $levels,
            'current' => (string) $this->course->currentLevel($user)['id'],
            'stats' => ['said' => $state['said'], 'talks' => count($state['scen']), 'best' => $state['best']],
        ];
    }

    /**
     * The conversations of a level, each with how many lines the learner says and the best score;
     * null when the level has no speaking content.
     *
     * @return list<array<string, mixed>>|null
     */
    public function talkList(User $user, string $levelId): ?array
    {
        $scenarios = $this->scenarios($levelId);

        if ($scenarios === null) {
            return null;
        }

        $scores = $this->speaking->state($user)['scen'];

        return array_map(fn (array $s): array => [
            'id' => $s['id'],
            'emoji' => $s['emoji'],
            'title' => $s['title'],
            'titleRo' => $s['titleRo'],
            'lines' => count($s['turns']),
            'score' => $scores[$s['id']] ?? null,
        ], $scenarios);
    }

    /**
     * A conversation of any level by its id, whole, or null.
     *
     * @return array<string, mixed>|null
     */
    public function scenario(string $id): ?array
    {
        foreach ($this->content->levels() as $level) {
            foreach ($this->scenarios($level['id']) ?? [] as $scenario) {
                if ($scenario['id'] === $id) {
                    return $scenario;
                }
            }
        }

        return null;
    }

    /**
     * A unit for the role-play (its dialogue), or null when there is no such unit or it is locked.
     *
     * @return array{id: string, emoji: mixed, title: mixed, dialogue: mixed}|null
     */
    public function roleUnit(User $user, string $unitId): ?array
    {
        $unit = $this->content->unit($unitId);

        if ($unit === null || ! $this->course->unitUnlocked($user, $unitId)) {
            return null;
        }

        return ['id' => $unitId, 'emoji' => $unit['emoji'], 'title' => $unit['title'], 'dialogue' => $unit['dialogue']];
    }

    /**
     * The phrase topics of a level (empty without speaking content), without their sentences.
     *
     * @return list<array{emoji: mixed, topic: mixed, topicRo: mixed}>
     */
    public function topics(string $levelId): array
    {
        return array_map(
            fn (array $t): array => ['emoji' => $t['emoji'], 'topic' => $t['topic'], 'topicRo' => $t['topicRo']],
            $this->phrases($levelId),
        );
    }

    /**
     * The sentences of a phrase topic (by its place in the list), or null for no such topic.
     *
     * @return array{emoji: mixed, topic: mixed, items: list<array<string, mixed>>}|null
     */
    public function topic(string $levelId, string $index): ?array
    {
        $topic = ctype_digit($index) ? ($this->phrases($levelId)[(int) $index] ?? null) : null;

        return $topic === null ? null : ['emoji' => $topic['emoji'], 'topic' => $topic['topic'], 'items' => array_values($topic['items'])];
    }

    /**
     * The lines of the dialogues of the open units of a level: {en, ro}.
     *
     * @return list<array{en: mixed, ro: mixed}>
     */
    public function lessonLines(User $user, string $levelId): array
    {
        $lines = [];

        foreach ($this->openUnits($user, $levelId) as $unit) {
            foreach ($unit['dialogue']['lines'] ?? [] as $line) {
                $lines[] = ['en' => $line['en'], 'ro' => $line['ro']];
            }
        }

        return $lines;
    }

    /**
     * "Say it in English": the sentences of the phrase topics and the examples of the words of the
     * open units, short enough to say in one go.
     *
     * @return list<array{en: mixed, ro: mixed}>
     */
    public function sayPool(User $user, string $levelId): array
    {
        $pool = [];

        foreach ($this->phrases($levelId) as $topic) {
            foreach ($topic['items'] as $item) {
                $pool[] = ['en' => $item['en'], 'ro' => $item['ro']];
            }
        }

        foreach ($this->openUnits($user, $levelId) as $unit) {
            foreach ($unit['vocab'] ?? [] as $word) {
                $pool[] = ['en' => $word['ex'], 'ro' => $word['exRo']];
            }
        }

        return array_values(array_filter(
            $pool,
            fn (array $item): bool => count(preg_split('/\s+/', (string) $item['en']) ?: []) <= self::SAY_MAX_WORDS,
        ));
    }

    /**
     * The open questions of a level, or null without speaking content.
     *
     * @return list<array<string, mixed>>|null
     */
    public function questions(string $levelId): ?array
    {
        $speaking = $this->content->speaking($levelId);

        /** @var list<array<string, mixed>>|null */
        return $speaking === null ? null : array_values($speaking['questions'] ?? []);
    }

    /**
     * The words of the voice sprint, {en, ro, emoji}: words the learner should already know
     * (learned, or from a level below the current one) that are short enough to say in one
     * breath; with fewer than 20 of them, the words the learner has met are added.
     *
     * @return list<array{en: string, ro: mixed, emoji: mixed}>
     */
    public function sprintWords(User $user): array
    {
        $below = (int) ($this->course->currentLevel($user)['index'] ?? 0);
        $levels = array_column($this->content->levels(), 'index', 'id');
        $pool = [];

        foreach ($this->content->words() as $word) {
            if ($this->isShort($word) && ($this->words->status($user, (string) $word['en']) === WordStatus::Learned || ($levels[$word['levelId']] ?? 0) < $below)) {
                $pool[(string) $word['en']] = $word;
            }
        }

        if (count($pool) < self::SPRINT_MIN_WORDS) {
            foreach ($this->words->knownWords($user, self::SPRINT_KNOWN_MIN) as $word) {
                if ($this->isShort($word)) {
                    $pool[(string) $word['en']] ??= $word;
                }
            }
        }

        return array_values(array_map(
            fn (array $word): array => ['en' => (string) $word['en'], 'ro' => $word['ro'], 'emoji' => $word['emoji'] ?? ''],
            $pool,
        ));
    }

    /**
     * The conversations of a level, or null when it has no speaking content.
     *
     * @return list<array<string, mixed>>|null
     */
    private function scenarios(string $levelId): ?array
    {
        $speaking = $this->content->speaking($levelId);

        /** @var list<array<string, mixed>>|null */
        return $speaking === null ? null : array_values($speaking['scenarios'] ?? []);
    }

    /** @return list<array<string, mixed>> */
    private function phrases(string $levelId): array
    {
        /** @var list<array<string, mixed>> */
        return array_values($this->content->speaking($levelId)['phrases'] ?? []);
    }

    /**
     * The open units of a level, whole.
     *
     * @return list<array<string, mixed>>
     */
    private function openUnits(User $user, string $levelId): array
    {
        return array_values(array_filter(
            $this->content->units($levelId),
            fn (array $unit): bool => $this->course->unitUnlocked($user, (string) $unit['id']),
        ));
    }

    /**
     * The open units of a level for the role-play list, with the best role-play score.
     *
     * @param  LevelOutline  $level
     * @param  array<string, float>  $scores
     * @return list<array<string, mixed>>
     */
    private function roleUnits(User $user, array $level, array $scores): array
    {
        return array_map(fn (array $unit): array => [
            'id' => $unit['id'],
            'order' => $unit['order'],
            'emoji' => $unit['emoji'],
            'title' => $unit['title'],
            'dialogue' => $unit['dialogue']['titleRo'] ?? '',
            'score' => $scores['role-'.$unit['id']] ?? null,
        ], $this->openUnits($user, $level['id']));
    }

    /** @param  array<string, mixed>  $word */
    private function isShort(array $word): bool
    {
        $en = (string) $word['en'];

        return count(preg_split('/\s+/', $en) ?: []) <= 2 && mb_strlen($en) <= 16;
    }
}
