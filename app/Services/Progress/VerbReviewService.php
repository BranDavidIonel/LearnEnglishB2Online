<?php

namespace App\Services\Progress;

use App\Enums\WordStatus;
use App\Models\User;
use App\Models\VerbReview;
use App\Services\Content\ContentRepository;

/**
 * The irregular verbs and their spaced repetition, like the words: a verb not in the
 * queue is "new", "learned" from box 3. A drill is graded 0 (wrong: back to box 0, again
 * today) or 2 (both forms right: one box up, next review after the interval of the box).
 * Verbs are arrays of ContentRepository::verbs() and are identified by their base form.
 */
class VerbReviewService
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly LearnerState $state,
    ) {}

    /**
     * The alternatives of a form: "learnt / learned" => ["learnt", "learned"].
     *
     * @return list<string>
     */
    public static function alternatives(string $form): array
    {
        return array_values(array_filter(array_map('trim', explode('/', $form)), fn (string $alt): bool => $alt !== ''));
    }

    /**
     * Every answer accepted when a form is typed: any alternative, or all of them
     * together ("was were", "was/were", "was / were").
     *
     * @return list<string>
     */
    public static function answers(string $form): array
    {
        $alternatives = self::alternatives($form);

        return [...$alternatives, implode(' ', $alternatives), implode('/', $alternatives), implode(' / ', $alternatives)];
    }

    public function status(User $user, string $v1): WordStatus
    {
        $review = $this->state->verbs($user)->get($v1);

        if ($review === null) {
            return WordStatus::New;
        }

        return $review->box >= (int) config('learning.rules.learned_box') ? WordStatus::Learned : WordStatus::Learning;
    }

    /** @return array{learned: int, learning: int, new: int, total: int} */
    public function counts(User $user): array
    {
        $counts = ['learned' => 0, 'learning' => 0, 'new' => 0, 'total' => 0];

        foreach ($this->content->verbs() as $verb) {
            $counts[$this->status($user, (string) $verb['v1'])->value]++;
            $counts['total']++;
        }

        return $counts;
    }

    /**
     * The verbs to review today, the most overdue first (equal dates: the most used verb first).
     *
     * @return list<array<string, mixed>>
     */
    public function due(User $user): array
    {
        $today = Calendar::todayString();
        $reviews = $this->state->verbs($user);
        $due = array_values(array_filter(
            $this->content->verbs(),
            fn (array $verb): bool => ($reviews->get($verb['v1'])?->due_on->toDateString() ?? '9999-12-31') <= $today,
        ));

        usort($due, fn (array $a, array $b): int => $this->dueDate($user, $a) <=> $this->dueDate($user, $b));

        return $due;
    }

    /** How many verbs are due today (the badge of the words menu). */
    public function dueCount(User $user): int
    {
        return count($this->due($user));
    }

    /** Grades a drill: 0 = wrong, 2 = both forms right. A verb not in the queue joins it. */
    public function grade(User $user, string $v1, int $grade): VerbReview
    {
        $review = $this->state->verbs($user)->get($v1) ?? $this->entry($user, $v1);
        $intervals = $this->intervals();

        if ($grade === 0) {
            $review->box = 0;
            $review->bad++;
            $review->due_on = Calendar::today();
        } else {
            $review->box = min($review->box + 1, count($intervals) - 1);
            $review->due_on = Calendar::today()->addDays($intervals[$review->box]);
            $review->ok++;
        }

        $review->save();

        return $review;
    }

    /** Both forms typed right before studying the verb: it counts as learned and comes back in a week as a check. */
    public function markKnown(User $user, string $v1): VerbReview
    {
        $box = (int) config('learning.rules.learned_box');

        return $this->replace($user, $v1, $box, $this->intervals()[$box], ok: 1, known: true);
    }

    /** Puts a verb into the review queue from the start (or again), due today. */
    public function learn(User $user, string $v1): VerbReview
    {
        return $this->replace($user, $v1, 0, 0, ok: 0, known: false);
    }

    /** Takes a verb out of the review queue. */
    public function forget(User $user, string $v1): void
    {
        $this->state->verbs($user)->get($v1)?->delete();
        $this->state->verbs($user)->forget($v1);
    }

    /**
     * A verb with the learner's state: status, box, due (Y-m-d), known.
     *
     * @param  array<string, mixed>  $verb
     * @return array<string, mixed>
     */
    public function withState(User $user, array $verb): array
    {
        $review = $this->state->verbs($user)->get((string) $verb['v1']);

        return [
            ...$verb,
            'status' => $this->status($user, (string) $verb['v1'])->value,
            'box' => $review?->box,
            'due' => $review?->due_on->toDateString(),
            'known' => (bool) $review?->known,
        ];
    }

    /** @param  array<string, mixed>  $verb */
    private function dueDate(User $user, array $verb): string
    {
        return (string) $this->state->verbs($user)->get($verb['v1'])?->due_on->toDateString();
    }

    private function replace(User $user, string $v1, int $box, int $inDays, int $ok, bool $known): VerbReview
    {
        $review = $this->state->verbs($user)->get($v1) ?? $this->entry($user, $v1);
        $review->fill(['box' => $box, 'due_on' => Calendar::inDays($inDays), 'ok' => $ok, 'bad' => 0, 'known' => $known]);
        $review->save();

        return $review;
    }

    /** A new queue entry (box 0, due today, not saved yet), already kept in the learner state. */
    private function entry(User $user, string $v1): VerbReview
    {
        $review = new VerbReview(['verb' => $v1, 'box' => 0, 'due_on' => Calendar::todayString(), 'ok' => 0, 'bad' => 0, 'known' => false]);
        $review->user_id = $user->id;
        $this->state->verbs($user)->put($v1, $review);

        return $review;
    }

    /** @return list<int> */
    private function intervals(): array
    {
        /** @var list<int> */
        return config('learning.rules.intervals');
    }
}
