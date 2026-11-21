<?php

use App\Services\Content\ContentRepository;
use App\Services\Progress\ExamService;
use App\Services\Progress\XpService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->exams = app(ExamService::class);
    $this->user = freshLearner();
});

/**
 * Exam answers: for each unit of A1, how many right out of 3.
 *
 * @param  array<string, int>  $right  unit => right answers (3 when not given)
 */
function a1ExamAnswers(array $right = []): array
{
    $results = [];

    foreach (unitsOf('a1') as $unit) {
        foreach (range(1, 3) as $n) {
            $results[] = ['unit' => $unit, 'correct' => $n <= ($right[$unit] ?? 3)];
        }
    }

    return $results;
}

it('builds an exam with three questions of every unit, no "match" exercise', function () {
    $items = $this->exams->buildExam('a1');
    $content = app(ContentRepository::class);

    expect($items)->toHaveCount(30)
        ->and(collect($items)->countBy('unit')->all())->toBe(array_fill_keys(unitsOf('a1'), 3))
        ->and(collect($items)->pluck('key')->unique())->toHaveCount(30);

    foreach ($items as $item) {
        [$unit, $index] = explode(':', $item['key']);
        expect($item['ex'])->toBe($content->unit($unit)['exercises'][(int) $index])
            ->and($item['ex']['type'])->not->toBe('match');
    }
});

it('passes an exam from 80%: 150 XP the first time, 20 after', function () {
    $first = $this->exams->recordExam($this->user, 'a1', a1ExamAnswers(['a1-u01' => 1, 'a1-u02' => 1, 'a1-u03' => 2]));

    expect($first['score'])->toEqualWithDelta(25 / 30, 1e-12)
        ->and($first)->toMatchArray(['passed' => true, 'first' => true, 'xp' => 150, 'weak' => ['a1-u01', 'a1-u02']])
        ->and($first['byUnit']['a1-u03'])->toBe(['ok' => 2, 'n' => 3]);

    $again = $this->exams->recordExam($this->user, 'a1', a1ExamAnswers());
    $exam = $this->user->examResults()->sole();

    expect($again)->toMatchArray(['passed' => true, 'first' => false, 'xp' => 20, 'weak' => []])
        ->and($exam->tries)->toBe(2)
        ->and($exam->best)->toBe(1.0)
        ->and($exam->last_taken_on->toDateString())->toBe('2026-11-21')
        ->and(app(XpService::class)->total($this->user))->toBe(170);
});

it('sends the weak units back to review when the exam is failed', function () {
    $result = $this->exams->recordExam($this->user, 'a1', a1ExamAnswers(['a1-u02' => 1, 'a1-u05' => 0, 'a1-u06' => 0, 'a1-u07' => 2]));

    expect($result)->toMatchArray(['passed' => false, 'first' => false, 'xp' => 0, 'weak' => ['a1-u02', 'a1-u05', 'a1-u06']])
        ->and($this->user->unitProgress()->where('review', true)->pluck('unit')->sort()->values()->all())->toBe(['a1-u02', 'a1-u05', 'a1-u06'])
        ->and($this->user->examResults()->sole()->passed)->toBeFalse()
        ->and($this->user->profile()->exists())->toBeFalse();
});

it('clears every review of the level when the exam is passed', function () {
    passUnits($this->user, ['a1-u02', 'a1-u05'], ['review' => true]);

    $this->exams->recordExam($this->user, 'a1', a1ExamAnswers(['a1-u05' => 1]));

    // Like the original, every unit of the level gets a state.
    expect($this->user->unitProgress()->count())->toBe(10)
        ->and($this->user->unitProgress()->where('review', true)->count())->toBe(0);
});

it('flags a weak unit with less than 60% right only', function () {
    $results = [
        ['unit' => 'a1-u01', 'correct' => true], ['unit' => 'a1-u01', 'correct' => true], ['unit' => 'a1-u01', 'correct' => false],
        ['unit' => 'a1-u01', 'correct' => false], ['unit' => 'a1-u01', 'correct' => true],
        ['unit' => 'a1-u02', 'correct' => false],
    ];

    expect($this->exams->recordExam($this->user, 'a1', $results)['weak'])->toBe(['a1-u02']);
});
