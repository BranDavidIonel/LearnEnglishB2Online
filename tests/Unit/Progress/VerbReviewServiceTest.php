<?php

use App\Enums\WordStatus;
use App\Services\Content\ContentRepository;
use App\Services\Progress\VerbReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-21 10:00');
    $this->verbs = app(VerbReviewService::class);
    $this->user = freshLearner();
});

/** The state of a verb in the queue: [box, due, ok, bad, known]. */
function verbQueueState(string $v1): array
{
    $review = test()->user->verbReviews()->where('verb', $v1)->sole();

    return [$review->box, $review->due_on->toDateString(), $review->ok, $review->bad, $review->known];
}

it('splits the alternatives of a form and accepts them all, alone or together', function () {
    expect(VerbReviewService::alternatives('learnt / learned'))->toBe(['learnt', 'learned'])
        ->and(VerbReviewService::alternatives(' went '))->toBe(['went'])
        ->and(VerbReviewService::alternatives('was/ /were'))->toBe(['was', 'were'])
        ->and(VerbReviewService::answers('was / were'))->toBe(['was', 'were', 'was were', 'was/were', 'was / were'])
        ->and(VerbReviewService::answers('went'))->toBe(['went', 'went', 'went', 'went']);
});

it('moves a verb up a box when both forms are right and back to 0 when wrong', function () {
    $this->verbs->grade($this->user, 'be', 2);
    expect(verbQueueState('be'))->toBe([1, '2026-11-22', 1, 0, false]);

    $this->verbs->grade($this->user, 'be', 2);
    $this->verbs->grade($this->user, 'be', 2);
    expect(verbQueueState('be'))->toBe([3, '2026-11-28', 3, 0, false])
        ->and($this->verbs->status($this->user, 'be'))->toBe(WordStatus::Learned);

    $this->verbs->grade($this->user, 'be', 0);
    expect(verbQueueState('be'))->toBe([0, '2026-11-21', 3, 1, false])
        ->and($this->verbs->status($this->user, 'be'))->toBe(WordStatus::Learning);
});

it('marks a verb known, to learn, or takes it out', function () {
    $this->verbs->markKnown($this->user, 'go');
    expect(verbQueueState('go'))->toBe([3, '2026-11-28', 1, 0, true]);

    $this->verbs->learn($this->user, 'go');
    expect(verbQueueState('go'))->toBe([0, '2026-11-21', 0, 0, false]);

    $this->verbs->forget($this->user, 'go');
    expect($this->verbs->status($this->user, 'go'))->toBe(WordStatus::New)
        ->and($this->user->verbReviews()->count())->toBe(0);
});

it('lists the verbs due today, most overdue first, the most used first on the same day', function () {
    $this->user->verbReviews()->createMany([
        ['verb' => 'feel', 'box' => 1, 'due_on' => '2026-11-20'],
        ['verb' => 'go', 'box' => 1, 'due_on' => '2026-11-21'],
        ['verb' => 'be', 'box' => 1, 'due_on' => '2026-11-21'],
        ['verb' => 'have', 'box' => 1, 'due_on' => '2026-11-25'],
    ]);

    expect(array_column($this->verbs->due($this->user), 'v1'))->toBe(['feel', 'be', 'go'])
        ->and($this->verbs->dueCount($this->user))->toBe(3);
});

it('counts the verbs of the list', function () {
    $this->verbs->markKnown($this->user, 'be');
    $this->verbs->learn($this->user, 'go');
    $this->verbs->learn($this->user, 'not a verb of the list');

    expect($this->verbs->counts($this->user))->toBe(['learned' => 1, 'learning' => 1, 'new' => 237, 'total' => 239]);
});

it('adds the learner state to a verb', function () {
    $this->verbs->markKnown($this->user, 'be');
    $verb = app(ContentRepository::class)->verb('be');

    expect($this->verbs->withState($this->user, $verb))->toMatchArray(['v1' => 'be', 'rank' => 1, 'status' => 'learned', 'box' => 3, 'due' => '2026-11-28', 'known' => true]);
});
