<?php

namespace App\Http\Controllers;

use App\Enums\Level;
use App\Http\Resources\WordResource;
use App\Http\Resources\Words\SortWordResource;
use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Course\UnitPage;
use App\Services\Progress\CourseProgressService;
use App\Services\Progress\WordReviewService;
use App\Services\Words\WordPages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The word bank of a unit: its extra words, sorted 20 at a time. It is a page of the unit,
 * so a unit that is not in the course or not open yet shows the unit's own screens for it.
 * After a batch the page reloads "words" and "todo".
 */
class WordBankController extends Controller
{
    public function __invoke(
        Request $request,
        string $unit,
        UnitPage $unitPage,
        CourseProgressService $course,
        WordReviewService $words,
        ContentRepository $content,
        WordPages $page,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $reason = $unitPage->blocked($user, $unit);

        if ($reason !== null) {
            return Inertia::render('units/Unavailable', ['reason' => $reason]);
        }

        /** @var array<string, mixed> $summary the unit exists (checked above) */
        $summary = $course->unitSummary($user, $unit);
        $level = Level::from((string) $summary['levelId']);
        $bank = $words->bankWords($unit);

        return Inertia::render('words/Bank', [
            'unit' => [
                'id' => $summary['id'],
                'emoji' => $summary['emoji'],
                'title' => $summary['title'],
                // For the step dots: { vocab: true, … }.
                'steps' => (object) array_fill_keys($summary['steps'], true),
                'passed' => $summary['passed'],
                // How many core words the "New words" step has.
                'vocab' => count($content->unitOutline($unit)['vocab'] ?? []),
            ],
            'level' => ['id' => $level->value, 'name' => $level->label(), 'color' => $level->color()],
            'words' => fn (): array => WordResource::collection($page->bank($user, $unit))->resolve($request),
            'choices' => fn (): array => $page->lessonChoices($bank),
            'units' => fn (): array => $page->unitLabels(array_values(array_unique(array_column($bank, 'unit')))),
            'todo' => function () use ($page, $user, $unit, $request): array {
                $todo = $page->batch($user, $page->bankToLearn($user, $unit));

                return ['count' => $todo['count'], 'batch' => SortWordResource::collection($todo['batch'])->resolve($request)];
            },
            'batchSize' => $page->batchSize(),
        ]);
    }
}
