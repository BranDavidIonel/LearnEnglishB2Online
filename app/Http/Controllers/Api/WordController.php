<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\WordLookupRequest;
use App\Http\Requests\Api\WordsRequest;
use App\Http\Resources\WordLookupResource;
use App\Http\Resources\WordResource;
use App\Models\User;
use App\Services\Progress\WordLookupService;
use App\Services\Progress\WordReviewService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * The flashcard deck: grading, "I know it", "learn it", "take it out" and the quick sort,
 * for one word or many at once (a whole list marked known, a sort batch), course words and
 * extra words of the glossary alike. Each answer returns the words with their new state.
 * And the word window: what a word tapped in a lesson is (a read, without the learner summary).
 */
class WordController extends ApiController
{
    public function lookup(WordLookupRequest $request, WordLookupService $lookup): JsonResponse
    {
        $found = $lookup->lookup($this->learner($request), $request->word(), $request->sentence());

        return response()->json(['lookup' => (new WordLookupResource($found))->resolve($request)]);
    }

    public function grade(WordsRequest $request, WordReviewService $words): JsonResponse
    {
        return $this->each($request, $words, fn (User $user, string $en) => $words->grade($user, $en, $request->integer('grade')));
    }

    public function known(WordsRequest $request, WordReviewService $words): JsonResponse
    {
        return $this->each($request, $words, fn (User $user, string $en) => $words->markKnown($user, $en));
    }

    public function sorted(WordsRequest $request, WordReviewService $words): JsonResponse
    {
        return $this->each($request, $words, fn (User $user, string $en) => $words->sortedKnown($user, $en));
    }

    public function learn(WordsRequest $request, WordReviewService $words): JsonResponse
    {
        return $this->each($request, $words, fn (User $user, string $en) => $words->learn($user, $en));
    }

    public function forget(WordsRequest $request, WordReviewService $words): JsonResponse
    {
        return $this->each($request, $words, fn (User $user, string $en) => $words->forget($user, $en));
    }

    /** Applies a rule to every word of the request, in one transaction. */
    private function each(WordsRequest $request, WordReviewService $words, Closure $rule): JsonResponse
    {
        $user = $this->learner($request);

        DB::transaction(function () use ($request, $user, $rule): void {
            foreach ($request->words() as $en) {
                $rule($user, $en);
            }
        });

        return $this->changed($request, [
            'words' => WordResource::collection(array_map(
                fn (string $en): array => $words->withState($user, (array) $words->card($en)),
                $request->words(),
            )),
        ]);
    }
}
