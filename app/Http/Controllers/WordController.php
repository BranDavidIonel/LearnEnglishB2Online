<?php

namespace App\Http\Controllers;

use App\Http\Resources\Words\WordRowResource;
use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Words\WordPages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My words": every word of the course with its status. As in the original, the page filters,
 * searches and pages the whole list itself, so it gets every word at once, but only what a
 * row shows; the details of the words (examples, pictures, units) follow in a deferred
 * request. A tab only swaps the "tab" prop, and after a change the page reloads "skipped".
 * The learner's extra words (glossary words in the deck) have a tab of their own.
 */
class WordController extends Controller
{
    public function __invoke(Request $request, WordPages $page, ContentRepository $content, ?string $tab = null): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('words/Index', [
            'tab' => $tab ?? 'all',
            'words' => fn (): array => WordRowResource::collection($page->all($user))->resolve($request),
            'extra' => fn (): array => WordRowResource::collection($page->extra($user))->resolve($request),
            'details' => Inertia::defer(fn (): array => $page->details(), 'details'),
            'units' => Inertia::defer(fn (): array => $page->unitLabels(), 'details'),
            'skipped' => fn (): ?array => $page->skipped($user),
            'verbs' => fn (): int => count($content->verbs()),
            'learnedBox' => (int) config('learning.rules.learned_box'),
            'batchSize' => $page->batchSize(),
        ]);
    }
}
