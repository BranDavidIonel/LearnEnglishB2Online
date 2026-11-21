<?php

namespace App\Http\Controllers;

use App\Http\Resources\LevelResource;
use App\Http\Resources\Words\SortWordResource;
use App\Models\User;
use App\Services\Progress\CourseProgressService;
use App\Services\Progress\WordReviewService;
use App\Services\Words\WordPages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The quick sort of a level's words, 20 at a time ("I knew it" / "New for me"). A level that
 * is not open yet shows a lock. After a batch the page reloads "stats" and "todo".
 */
class WordSortController extends Controller
{
    public function __invoke(Request $request, string $level, CourseProgressService $course, WordReviewService $words, WordPages $page): Response
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array<string, mixed> $summary the route only lets the course levels through */
        $summary = $course->levelSummary($user, $level);
        $open = (bool) $summary['unlocked'];

        return Inertia::render('words/Sort', [
            'level' => (new LevelResource($summary))->resolve($request),
            'stats' => fn (): ?array => $open ? $words->counts($user, $words->levelWords($level)) : null,
            'todo' => function () use ($open, $page, $user, $words, $level, $request): ?array {
                if (! $open) {
                    return null;
                }

                $todo = $page->batch($user, $words->wordsToLearn($user, $level));

                return ['count' => $todo['count'], 'batch' => SortWordResource::collection($todo['batch'])->resolve($request)];
            },
            'batchSize' => $page->batchSize(),
        ]);
    }
}
