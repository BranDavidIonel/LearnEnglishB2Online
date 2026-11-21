<?php

namespace App\Services\Cambridge;

use App\Enums\CambridgePaper;
use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\CambridgeService;

/**
 * What the Cambridge pages show: the list of exams, one exam with its mock tests, and one
 * paper of a test. A mock test file is large, so a paper page gets only that paper of
 * that test; the exam page gets only how long each paper is and how many questions it has.
 */
class CambridgePages
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly CambridgeService $cambridge,
    ) {}

    /** @return array<string, mixed>|null the exam (with its tests), null when it is not in the content */
    public function findExam(string $examId): ?array
    {
        return $this->content->cambridgeExam($examId);
    }

    /**
     * @param  array<string, mixed>  $exam
     * @return array<string, mixed>|null
     */
    public function findTest(array $exam, string $testId): ?array
    {
        foreach ($exam['tests'] ?? [] as $test) {
            if ($test['id'] === $testId) {
                return $test;
            }
        }

        return null;
    }

    /**
     * The exams, each with how many mock tests it has and the verdict on the learner.
     *
     * @return array<string, mixed>
     */
    public function index(User $user): array
    {
        return [
            'exams' => array_map(function (array $exam) use ($user): array {
                $readiness = $this->cambridge->readiness($user, $exam['id']);

                return [
                    ...$this->examTag($exam),
                    'tests' => count($exam['tests'] ?? []),
                    'overall' => $readiness['overall'],
                    'verdict' => $readiness['verdict'],
                ];
            }, $this->content->cambridge()),
        ];
    }

    /**
     * One exam: the readiness card, every mock test with the size of its papers and the
     * learner's latest result of each, and what the real exam is like.
     *
     * @param  array<string, mixed>  $exam
     * @return array<string, mixed>
     */
    public function exam(User $user, array $exam): array
    {
        $results = $this->cambridge->tests($user, $exam['id']);

        return [
            'exam' => [
                ...$this->examTag($exam),
                'aboutRo' => $exam['aboutRo'] ?? '',
                'papersRo' => $exam['papersRo'] ?? [],
            ],
            'readiness' => $this->cambridge->readiness($user, $exam['id']),
            'tests' => array_map(fn (array $test): array => [
                ...$this->testTag($test),
                'papers' => $this->papers($test),
                'results' => array_intersect_key($results[$test['id']] ?? [], array_flip(CambridgePaper::values())),
            ], $exam['tests'] ?? []),
            ...$this->marks(),
        ];
    }

    /**
     * A Reading or Listening paper: its parts and questions, as in the content.
     *
     * @param  array<string, mixed>  $exam
     * @param  array<string, mixed>  $test
     * @return array<string, mixed>
     */
    public function paper(array $exam, array $test, CambridgePaper $paper): array
    {
        return [
            'exam' => $this->examTag($exam),
            'test' => $this->testTag($test),
            'paper' => $paper->value,
            'data' => $test[$paper->value],
            ...$this->marks(),
        ];
    }

    /**
     * The Writing paper, with the drafts the learner left (one text per task).
     *
     * @param  array<string, mixed>  $exam
     * @param  array<string, mixed>  $test
     * @return array<string, mixed>
     */
    public function writing(User $user, array $exam, array $test): array
    {
        $stored = $this->cambridge->state($user, $exam['id'], $test['id'])['drafts'] ?? [];
        $drafts = [];

        foreach (array_keys($test['writing']['tasks']) as $task) {
            $drafts[] = (string) ($stored[$task] ?? '');
        }

        return [
            ...$this->paper($exam, $test, CambridgePaper::Writing),
            'drafts' => $drafts,
        ];
    }

    /**
     * Minutes and number of questions (Reading, Listening) or tasks (Writing, Speaking).
     *
     * @param  array<string, mixed>  $test
     * @return list<array{id: string, minutes: int, count: int}>
     */
    private function papers(array $test): array
    {
        return array_map(function (CambridgePaper $paper) use ($test): array {
            $data = $test[$paper->value];
            $count = $paper->isMarked()
                ? array_sum(array_map(fn (array $part): int => count($part['questions']), $data['parts']))
                : count($data['tasks']);

            return ['id' => $paper->value, 'minutes' => (int) $data['minutes'], 'count' => $count];
        }, CambridgePaper::cases());
    }

    /**
     * @param  array<string, mixed>  $exam
     * @return array{id: string, name: string, level: string}
     */
    private function examTag(array $exam): array
    {
        return ['id' => $exam['id'], 'name' => $exam['name'], 'level' => $exam['level']];
    }

    /**
     * @param  array<string, mixed>  $test
     * @return array{id: string, title: string}
     */
    private function testTag(array $test): array
    {
        return ['id' => $test['id'], 'title' => $test['title']];
    }

    /**
     * The safe target of a mock and the "close" score: the pages colour results with them.
     *
     * @return array{target: float, close: float}
     */
    private function marks(): array
    {
        return [
            'target' => (float) config('learning.rules.cambridge_target'),
            'close' => (float) config('learning.rules.cambridge_close'),
        ];
    }
}
