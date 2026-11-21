<?php

namespace App\Services\Progress;

use App\Enums\CambridgePaper;
use App\Enums\Readiness;
use App\Models\CambridgeTest;
use App\Models\User;
use App\Services\Content\ContentRepository;

/**
 * The Cambridge mock exams (original camState, recordCam, camReadiness): the latest result
 * of every paper of every test, the writing drafts, and how ready the learner is for an
 * exam. A paper marked by the app (Reading, Listening) gives 40 XP, a self-assessed one 20.
 */
class CambridgeService
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly CourseProgressService $course,
        private readonly XpService $xp,
    ) {}

    /**
     * What the learner has stored for every test of an exam, as the original kept it:
     * test id => {reading: {score, …, date}, …, drafts: {"0": "…"}}.
     *
     * @return array<string, array<string, mixed>>
     */
    public function tests(User $user, string $examId): array
    {
        $tests = [];

        foreach ($this->testRows($user, $examId) as $test) {
            $tests[$test->test] = $this->stateOf($test);
        }

        return $tests;
    }

    /**
     * What the learner has stored for one test (an empty array when nothing yet).
     *
     * @return array<string, mixed>
     */
    public function state(User $user, string $examId, string $testId): array
    {
        return $this->tests($user, $examId)[$testId] ?? [];
    }

    /**
     * Stores the result of a paper (it replaces the previous one of that paper and test).
     *
     * @param  array<string, mixed>  $data  {score 0..1, correct?, total?, parts?}
     * @return array{result: array<string, mixed>, xp: int}
     */
    public function record(User $user, string $examId, string $testId, CambridgePaper $paper, array $data): array
    {
        $test = $this->testRow($user, $examId, $testId);
        unset($data['date']);

        $test->results()->updateOrCreate(
            ['paper' => $paper->value],
            ['user_id' => $user->id, 'result' => $data, 'taken_on' => Calendar::today()],
        );

        $xp = (int) config($paper->isMarked() ? 'learning.xp.cambridge_marked' : 'learning.xp.cambridge_rated');
        $this->xp->award($user, $xp);

        return ['result' => [...$data, 'date' => Calendar::todayString()], 'xp' => $xp];
    }

    /** Keeps the draft of a writing task (an empty text keeps an empty draft, like the original). */
    public function saveDraft(User $user, string $examId, string $testId, int $task, string $text): void
    {
        $test = $this->testRow($user, $examId, $testId);
        $drafts = $test->drafts ?? [];
        $drafts[$task] = $text;
        $test->drafts = $drafts;
        $test->save();
    }

    /**
     * How ready the learner is for an exam: the latest result of each paper (over every
     * test), how much of the course up to the exam's level is done, the average of those
     * results and the verdict. "ready" needs all four papers, Reading and Listening and the
     * average at 70% or more; "close" an average of 60%; "unknown" until Reading or
     * Listening is done.
     *
     * @return array{latest: array<string, array<string, mixed>>, course: float, overall: float|null, verdict: Readiness, done: int}
     */
    public function readiness(User $user, string $examId): array
    {
        $exam = $this->content->cambridgeExam($examId);
        $latest = $this->latest($user, $examId);
        $scores = array_values(array_map(fn (array $result): float => (float) ($result['score'] ?? 0), $latest));
        $marked = array_filter($latest, fn (string $paper): bool => CambridgePaper::from($paper)->isMarked(), ARRAY_FILTER_USE_KEY);
        $overall = $scores === [] ? null : array_sum($scores) / count($scores);

        return [
            'latest' => $latest,
            'course' => $this->course->progressTo($user, strtolower((string) ($exam['level'] ?? ''))),
            'overall' => $overall,
            'verdict' => $this->verdict($marked, count($scores), $overall),
            'done' => count($scores),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $marked  the latest Reading / Listening results
     */
    private function verdict(array $marked, int $done, ?float $overall): Readiness
    {
        if ($marked === []) {
            return Readiness::Unknown;
        }

        $target = (float) config('learning.rules.cambridge_target');
        $lowest = min(array_map(fn (array $result): float => (float) ($result['score'] ?? 0), $marked));

        if (count($marked) === 2 && $done === count(CambridgePaper::cases()) && $lowest >= $target && $overall >= $target) {
            return Readiness::Ready;
        }

        return $overall >= (float) config('learning.rules.cambridge_close') ? Readiness::Close : Readiness::NotYet;
    }

    /**
     * The most recent result of each paper over every test of the exam (on the same day,
     * the test opened last wins), in the order of the papers.
     *
     * @return array<string, array<string, mixed>>
     */
    private function latest(User $user, string $examId): array
    {
        $tests = $this->tests($user, $examId);
        $latest = [];

        foreach (CambridgePaper::values() as $paper) {
            foreach ($tests as $test) {
                $result = $test[$paper] ?? null;

                if (is_array($result) && (! isset($latest[$paper]) || (string) ($result['date'] ?? '') >= (string) ($latest[$paper]['date'] ?? ''))) {
                    $latest[$paper] = $result;
                }
            }
        }

        return $latest;
    }

    /** @return array<string, mixed> */
    private function stateOf(CambridgeTest $test): array
    {
        $state = [];

        foreach ($test->results as $result) {
            $state[$result->paper] = [...$result->result, ...($result->taken_on ? ['date' => $result->taken_on->toDateString()] : [])];
        }

        if ($test->drafts !== null) {
            $state['drafts'] = $test->drafts;
        }

        return $state;
    }

    /** @return iterable<CambridgeTest> */
    private function testRows(User $user, string $examId): iterable
    {
        return $user->cambridgeTests()->where('exam', $examId)->with('results')->orderBy('id')->get();
    }

    private function testRow(User $user, string $examId, string $testId): CambridgeTest
    {
        return $user->cambridgeTests()->firstOrCreate(['exam' => $examId, 'test' => $testId]);
    }
}
