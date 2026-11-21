<?php

use App\Models\User;
use App\Services\Content\ContentRepository;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->actingAs(User::factory()->create());
});

/**
 * Exam answers for A1: three per unit, the units in `$wrong` all wrong.
 *
 * @param  list<int>  $wrong  unit numbers
 * @return list<array{unit: string, correct: bool}>
 */
function examPageAnswers(array $wrong): array
{
    $results = [];

    foreach (range(1, 10) as $n) {
        foreach (range(1, 3) as $i) {
            $results[] = ['unit' => sprintf('a1-u%02d', $n), 'correct' => ! in_array($n, $wrong, true)];
        }
    }

    return $results;
}

it('builds the exam of an open level: three questions from every unit', function () {
    $this->get(route('exams.show', 'a1'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('exams/Show')
        ->where('state', 'ready')
        ->where('level', ['id' => 'a1', 'name' => 'A1', 'title' => 'Beginner', 'color' => '#22c55e'])
        ->where('previous', null)
        ->has('units', 10)
        ->where('units.0.id', 'a1-u01')
        ->where('units.0.order', 1)
        ->has('units.0.emoji')
        ->where('review', [])
        ->where('exam', null)
        ->where('passed', false)
        ->where('notPassed', 10)
        ->where('questions', 30)
        ->has('items', 30)
        ->where('items', fn ($items) => collect($items)->every(fn (array $item) => $item['ex']['type'] !== 'match'
            && preg_match('/^a1-u\d{2}:\d+$/', $item['key']) === 1
            && $item['ex'] === app(ContentRepository::class)->unit($item['unit'])['exercises'][(int) explode(':', $item['key'])[1]]))
        ->where('next', ['name' => 'A2', 'firstUnit' => 'a2-u01'])
        ->where('passMark', 0.8));
});

it('shows a locked level without questions', function () {
    $this->get(route('exams.show', 'a2'))->assertInertia(fn (Assert $page) => $page
        ->where('state', 'locked')
        ->where('previous', 'A1')
        ->where('items', []));
});

it('asks for the units in review first after a failed exam', function () {
    $this->postJson(route('api.exams.store', 'a1'), ['results' => examPageAnswers([2, 3, 4])]);

    $this->get(route('exams.show', 'a1'))->assertInertia(fn (Assert $page) => $page
        ->where('state', 'review')
        ->where('review', ['a1-u02', 'a1-u03', 'a1-u04'])
        ->where('exam.tries', 1)
        ->where('exam.passed', false)
        ->where('items', []));
});

it('shows the best result and offers the exam again once it is passed', function () {
    $this->postJson(route('api.exams.store', 'a1'), ['results' => examPageAnswers([1])]);

    $this->get(route('exams.show', 'a1'))->assertInertia(fn (Assert $page) => $page
        ->where('state', 'ready')
        ->where('passed', true)
        ->where('exam', ['best' => 0.9, 'passed' => true, 'tries' => 1, 'last' => '2026-11-21'])
        ->has('items', 30));

    $this->get(route('exams.show', 'a2'))->assertInertia(fn (Assert $page) => $page->where('state', 'ready'));
});

it('has no next level after B2', function () {
    $this->postJson(route('api.placement.store'), ['scores' => ['a1' => 1, 'a2' => 1, 'b1' => 1, 'b2' => 0.1]]);

    $this->get(route('exams.show', 'b2'))->assertInertia(fn (Assert $page) => $page
        ->where('state', 'ready')
        ->where('previous', 'B1')
        ->where('next', null));
});

it('does not know other levels', function () {
    $this->get('/exams/c1')->assertNotFound();
});
