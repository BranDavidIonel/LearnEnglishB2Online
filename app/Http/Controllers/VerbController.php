<?php

namespace App\Http\Controllers;

use App\Http\Resources\Verbs\VerbItemResource;
use App\Models\User;
use App\Services\Progress\VerbReviewService;
use App\Services\Progress\WordReviewService;
use App\Services\Verbs\VerbsPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The irregular verbs: a tab of the "My words" page. Learning, testing and reviewing happen on
 * the page; after a session it reloads "verbs", "counts" and "due" (the props are lazy, so a
 * partial reload computes only what it asks for).
 */
class VerbController extends Controller
{
    public function __invoke(Request $request, VerbsPage $page, VerbReviewService $verbs, WordReviewService $words): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('verbs/Index', [
            // The numbers of the word tabs above the verbs.
            'wordCounts' => fn (): array => $words->counts($user),
            'counts' => fn (): array => $verbs->counts($user),
            'verbs' => fn (): array => VerbItemResource::collection($page->verbs($user))->resolve($request),
            'due' => fn (): array => $page->due($user),
        ]);
    }
}
