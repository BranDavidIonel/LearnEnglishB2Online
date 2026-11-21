<?php

namespace App\Services\Progress;

use App\Models\CambridgeTest;
use App\Models\ExamResult;
use App\Models\UnitProgress;
use App\Models\User;
use App\Models\VerbReview;
use App\Models\WordReview;
use stdClass;

/**
 * Writes a learner's progress in the format of the original app's progress.json, so it
 * can be kept as a backup, opened by the original app, or imported again
 * (ProgressImporter). Review days go back to day numbers; maps stay JSON objects even
 * when empty ({}), and whole numbers are written without decimals, like in the original file.
 */
class ProgressExporter
{
    public function __construct(
        private readonly LearnerState $state,
        private readonly XpService $xp,
        private readonly HistoryService $history,
        private readonly PlacementService $placement,
        private readonly SettingsService $settings,
        private readonly SpeakingService $speaking,
        private readonly GameService $games,
    ) {}

    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        $this->state->forget($user);
        $profile = $this->state->profile($user);
        $speak = $this->speaking->state($user);
        $placement = $this->placement->placement($user);

        return [
            'v' => 1,
            'xp' => $profile->xp,
            'goal' => $profile->goal,
            'streak' => ['count' => $profile->streak_count, 'last' => $profile->streak_last],
            'days' => $this->map($this->xp->days($user)),
            'placement' => $placement === null ? null : [
                'start' => $placement['start'],
                'scores' => $this->map($placement['scores']),
                'date' => $placement['date'],
            ],
            'units' => $this->map($this->state->units($user)->mapWithKeys(fn (UnitProgress $unit): array => [$unit->unit => $this->unit($unit)])->all()),
            'exams' => $this->map($this->state->exams($user)->mapWithKeys(fn (ExamResult $exam): array => [$exam->level => $this->exam($exam)])->all()),
            'words' => $this->map($this->state->words($user)->mapWithKeys(fn (WordReview $review): array => [$review->word => $this->review($review)])->all()),
            'verbs' => $this->map($this->state->verbs($user)->mapWithKeys(fn (VerbReview $review): array => [$review->verb => $this->review($review)])->all()),
            'mistakes' => array_values($this->state->mistakes($user)->pluck('key')->all()),
            'games' => $this->map($this->games->records($user)),
            'settings' => $this->settings->settings($user),
            'history' => $this->map($this->history->history($user)),
            'cambridge' => $this->map($this->cambridge($user)),
            'speak' => [
                'said' => $speak['said'],
                'scen' => $this->map($speak['scen']),
                'best' => $this->map($speak['best']),
            ],
            'flags' => $this->map($profile->flags ?? []),
        ];
    }

    /** The export as the text of a progress.json file. */
    public function toJson(User $user): string
    {
        return json_encode($this->export($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }

    /** @return array<string, mixed> */
    private function unit(UnitProgress $unit): array
    {
        return [
            'steps' => $this->map(array_fill_keys($unit->steps, true)),
            'best' => $unit->best,
            'passed' => $unit->passed,
            'review' => $unit->review,
            'tries' => $unit->tries,
        ];
    }

    /** @return array<string, mixed> */
    private function exam(ExamResult $exam): array
    {
        return [
            'best' => $exam->best,
            'passed' => $exam->passed,
            'tries' => $exam->tries,
            ...($exam->last_taken_on ? ['last' => $exam->last_taken_on->toDateString()] : []),
        ];
    }

    /** @return array<string, mixed> */
    private function review(WordReview|VerbReview $review): array
    {
        return [
            'box' => $review->box,
            'due' => Calendar::dayNumber($review->due_on),
            'ok' => $review->ok,
            'bad' => $review->bad,
            ...($review->known ? ['known' => true] : []),
        ];
    }

    /** @return array<string, stdClass> exam => {test => stored object} */
    private function cambridge(User $user): array
    {
        $exams = [];

        foreach ($user->cambridgeTests()->with('results')->orderBy('id')->get() as $test) {
            $exams[$test->exam][$test->test] = $this->cambridgeTest($test);
        }

        return array_map($this->map(...), $exams);
    }

    private function cambridgeTest(CambridgeTest $test): stdClass
    {
        $state = [];

        foreach ($test->results->sortBy('id') as $result) {
            $state[$result->paper] = $this->map([
                ...$result->result,
                ...($result->taken_on ? ['date' => $result->taken_on->toDateString()] : []),
            ]);
        }

        if ($test->drafts !== null) {
            $state['drafts'] = $this->map($test->drafts);
        }

        return $this->map($state);
    }

    /**
     * A JSON object, also when empty or when its keys look like list indexes.
     *
     * @param  array<int|string, mixed>  $values
     */
    private function map(array $values): stdClass
    {
        return (object) $values;
    }
}
