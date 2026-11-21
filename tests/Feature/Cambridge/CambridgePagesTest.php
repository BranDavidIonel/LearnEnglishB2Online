<?php

use App\Models\User;
use App\Services\Content\ContentRepository;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/** Stores a paper result through the progress API, as the pages do. */
function cambridgeResult(string $exam, string $test, string $paper, array $result): void
{
    test()->postJson(route('api.cambridge.store', [$exam, $test, $paper]), $result)->assertOk();
}

it('sends a guest to the login page', function (string $uri) {
    auth()->logout();

    $this->get($uri)->assertRedirect(route('login'));
})->with(['/cambridge', '/cambridge/pet', '/cambridge/pet/pet-test1/reading', '/cambridge/fce/fce-test2/writing']);

it('lists the two exams with the verdict of a new learner', function () {
    $this->get(route('cambridge'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('cambridge/Index')
        ->has('exams', 2)
        ->where('exams.0', ['id' => 'pet', 'name' => 'B1 Preliminary (PET)', 'level' => 'B1', 'tests' => 2, 'overall' => null, 'verdict' => 'unknown'])
        ->where('exams.1.id', 'fce')
        ->where('exams.1.level', 'B2'));
});

it('gives the verdict from the latest results', function () {
    cambridgeResult('pet', 'pet-test1', 'reading', ['score' => 0.5, 'correct' => 16, 'total' => 32, 'parts' => [3, 3, 3, 3, 2, 2]]);
    cambridgeResult('pet', 'pet-test1', 'listening', ['score' => 0.7, 'correct' => 17, 'total' => 25, 'parts' => [5, 4, 4, 4]]);

    $this->get(route('cambridge'))->assertInertia(fn (Assert $page) => $page
        ->where('exams.0.overall', 0.6)
        ->where('exams.0.verdict', 'close')
        ->where('exams.1.verdict', 'unknown'));
});

it('shows an exam: readiness, the papers of each test and the results', function () {
    cambridgeResult('pet', 'pet-test2', 'reading', ['score' => 0.8125, 'correct' => 26, 'total' => 32, 'parts' => [5, 5, 4, 3, 5, 4]]);
    cambridgeResult('pet', 'pet-test2', 'writing', ['score' => 0.6]);

    $this->get(route('cambridge', ['exam' => 'pet']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('cambridge/Exam')
        ->where('exam.id', 'pet')
        ->where('exam.level', 'B1')
        ->has('exam.aboutRo')
        ->has('exam.papersRo', 4)
        ->missing('exam.tests')
        ->where('readiness.verdict', 'close')
        ->where('readiness.done', 2)
        ->where('readiness.latest.reading.date', '2026-11-21')
        ->where('readiness.course', 0)
        ->has('tests', 2)
        ->where('tests.0', fn ($test) => $test['id'] === 'pet-test1' && $test['title'] === 'Simulare 1' && $test['results'] === [])
        ->where('tests.0.papers', [
            ['id' => 'reading', 'minutes' => 45, 'count' => 32],
            ['id' => 'listening', 'minutes' => 30, 'count' => 25],
            ['id' => 'writing', 'minutes' => 45, 'count' => 3],
            ['id' => 'speaking', 'minutes' => 12, 'count' => 4],
        ])
        ->where('tests.1.results.reading', ['score' => 0.8125, 'correct' => 26, 'total' => 32, 'parts' => [5, 5, 4, 3, 5, 4], 'date' => '2026-11-21'])
        ->where('tests.1.results.writing', ['score' => 0.6, 'date' => '2026-11-21'])
        ->where('target', 0.7)
        ->where('close', 0.6));
});

it('keeps the drafts out of the results of the exam page', function () {
    $this->putJson(route('api.cambridge.draft', ['fce', 'fce-test1']), ['task' => 0, 'text' => 'Some people say…'])->assertOk();

    $this->get(route('cambridge', ['exam' => 'fce']))->assertInertia(fn (Assert $page) => $page
        ->where('tests.0.results', [])
        ->where('tests.0.papers.0', ['id' => 'reading', 'minutes' => 75, 'count' => 52]));
});

it('sends only the paper that is opened', function (string $exam, string $test, string $paper) {
    $content = app(ContentRepository::class)->cambridgeExam($exam);
    $data = collect($content['tests'])->firstWhere('id', $test)[$paper];

    $this->get(route('cambridge', ['exam' => $exam, 'test' => $test, 'paper' => $paper]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('cambridge/Paper')
        ->where('exam', ['id' => $exam, 'name' => $content['name'], 'level' => $content['level']])
        ->where('test.id', $test)
        ->where('paper', $paper)
        ->where('data', $data)
        ->missing('test.writing')
        ->where('target', 0.7));
})->with([
    ['pet', 'pet-test1', 'reading'],
    ['pet', 'pet-test2', 'listening'],
    ['fce', 'fce-test1', 'reading'],
    ['fce', 'fce-test2', 'listening'],
]);

it('opens the writing paper with the drafts of every task', function () {
    $this->putJson(route('api.cambridge.draft', ['pet', 'pet-test1']), ['task' => 1, 'text' => 'My favourite place'])->assertOk();

    $this->get(route('cambridge', ['exam' => 'pet', 'test' => 'pet-test1', 'paper' => 'writing']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('cambridge/Writing')
        ->where('paper', 'writing')
        ->where('data.minutes', 45)
        ->has('data.tasks', 3)
        ->where('drafts', ['', 'My favourite place', '']));
});

it('opens the writing paper of a new learner with empty drafts', function () {
    $this->get(route('cambridge', ['exam' => 'fce', 'test' => 'fce-test2', 'paper' => 'writing']))->assertInertia(fn (Assert $page) => $page
        ->where('drafts', ['', '', '']));
});

it('opens the speaking paper', function () {
    $this->get(route('cambridge', ['exam' => 'fce', 'test' => 'fce-test1', 'paper' => 'speaking']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('cambridge/Speaking')
        ->where('test', ['id' => 'fce-test1', 'title' => 'Simulare 1'])
        ->where('data.minutes', 14)
        ->has('data.tasks', 4));
});

it('says an exam that is not in the course is not available', function () {
    $this->get('/cambridge/ielts')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('cambridge/Unavailable'));
});

it('sends an unknown test or paper back to the exam', function (string $uri) {
    $this->get($uri)->assertRedirect('/cambridge/pet');
})->with(['/cambridge/pet/pet-test1', '/cambridge/pet/pet-test9/reading', '/cambridge/pet/fce-test1/reading', '/cambridge/pet/pet-test1/grammar']);
