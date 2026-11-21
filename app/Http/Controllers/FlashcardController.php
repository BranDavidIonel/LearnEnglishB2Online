<?php

namespace App\Http\Controllers;

use App\Http\Resources\WordResource;
use App\Models\User;
use App\Services\Progress\WordReviewService;
use App\Services\Words\WordPages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The flashcards: today's reviews with spaced repetition, and free practice. The cards of
 * both sessions come ready with the page; after a session the page reloads everything.
 */
class FlashcardController extends Controller
{
    public function __invoke(Request $request, WordPages $page, WordReviewService $words): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('words/Flashcards', [
            'inDeck' => fn (): int => $page->inDeck($user),
            'dueCount' => fn (): int => $words->dueCount($user),
            'counts' => fn (): array => $words->counts($user),
            'due' => fn (): array => WordResource::collection($page->due($user))->resolve($request),
            'practice' => fn (): array => WordResource::collection($page->practice($user))->resolve($request),
        ]);
    }
}
