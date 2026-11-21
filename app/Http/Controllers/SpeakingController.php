<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Speaking\SpeakingPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Speaking practice: the hub, then one mode — conversations, role-play with a unit dialogue,
 * repeat after me, say it in English, open questions, sound-alike words and the voice sprint.
 * The level of the content comes as ?level= (the hub's level picker is a preference of the
 * device); without it, or when that level is locked, the level being worked on is used.
 */
class SpeakingController extends Controller
{
    public function __construct(private readonly SpeakingPages $pages) {}

    public function __invoke(Request $request, ?string $mode = null, ?string $arg = null): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $asked = $request->query('level');
        $level = $this->pages->chosenLevel($user, is_string($asked) ? $asked : null)['id'];

        return match ($mode) {
            'talk' => $this->talk($user, $level, $arg),
            'role' => $this->role($user, $arg),
            'repeat' => $this->repeat($user, $level, $arg),
            'say' => $this->say($user, $level),
            'answer' => $this->answer($user, $level),
            'pairs' => Inertia::render('speaking/Pairs'),
            'sprint' => Inertia::render('speaking/Sprint', ['words' => $this->pages->sprintWords($user)]),
            default => Inertia::render('speaking/Index', $this->pages->hub($user)),
        };
    }

    /** The conversations of the level, or one conversation (of any level) by its id. */
    private function talk(User $user, string $level, ?string $scenarioId): Response|RedirectResponse
    {
        if ($scenarioId !== null) {
            $scenario = $this->pages->scenario($scenarioId);

            return $scenario === null
                ? to_route('speaking', ['mode' => 'talk', 'level' => $level])
                : Inertia::render('speaking/Talk', ['scenario' => $scenario]);
        }

        return Inertia::render('speaking/Talk', [
            ...$this->levelProps($user, $level),
            'scenarios' => $this->pages->talkList($user, $level),
        ]);
    }

    /** Role-play with the dialogue of an open unit. */
    private function role(User $user, ?string $unitId): Response|RedirectResponse
    {
        $unit = $unitId === null ? null : $this->pages->roleUnit($user, $unitId);

        return $unit === null ? to_route('speaking') : Inertia::render('speaking/Role', ['unit' => $unit]);
    }

    /** The phrase topics of the level, or a round of one topic ("lessons": the lines of the open units). */
    private function repeat(User $user, string $level, ?string $topic): Response|RedirectResponse
    {
        if ($topic === null) {
            return Inertia::render('speaking/Sentences', [
                ...$this->levelProps($user, $level),
                'mode' => 'repeat',
                'topics' => $this->pages->topics($level),
                'lessons' => $this->pages->lessonLines($user, $level) !== [],
                'round' => null,
            ]);
        }

        $round = $topic === 'lessons'
            ? ['kind' => 'lessons', 'title' => null, 'items' => $this->pages->lessonLines($user, $level)]
            : $this->topicRound($level, $topic);

        if ($round === null || $round['items'] === []) {
            return to_route('speaking', ['mode' => 'repeat', 'level' => $level]);
        }

        return Inertia::render('speaking/Sentences', [...$this->levelProps($user, $level), 'mode' => 'repeat', 'round' => $round]);
    }

    /** Say it in English: sentences of the level seen in Romanian. */
    private function say(User $user, string $level): Response
    {
        return Inertia::render('speaking/Sentences', [
            ...$this->levelProps($user, $level),
            'mode' => 'say',
            'round' => ['kind' => 'say', 'title' => null, 'items' => $this->pages->sayPool($user, $level)],
        ]);
    }

    /** Open questions about the learner. */
    private function answer(User $user, string $level): Response
    {
        return Inertia::render('speaking/Answer', [
            ...$this->levelProps($user, $level),
            'questions' => $this->pages->questions($level),
        ]);
    }

    /**
     * A round of a phrase topic, or null for no such topic.
     *
     * @return array{kind: string, title: string, items: list<array<string, mixed>>}|null
     */
    private function topicRound(string $level, string $index): ?array
    {
        $topic = $this->pages->topic($level, $index);

        return $topic === null ? null : ['kind' => 'topic', 'title' => "{$topic['emoji']} {$topic['topic']}", 'items' => $topic['items']];
    }

    /**
     * The level shown and the open levels (the page checks the device's chosen level against them).
     *
     * @return array{level: string, open: list<string>}
     */
    private function levelProps(User $user, string $level): array
    {
        return ['level' => $level, 'open' => $this->pages->openLevels($user)];
    }
}
