<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\VerbsRequest;
use App\Http\Resources\VerbResource;
use App\Models\User;
use App\Services\Progress\VerbReviewService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** The irregular verbs review queue, for one verb or many at once. Each answer returns the verbs with their new state. */
class VerbController extends ApiController
{
    public function grade(VerbsRequest $request, VerbReviewService $verbs): JsonResponse
    {
        return $this->each($request, $verbs, fn (User $user, string $v1) => $verbs->grade($user, $v1, $request->integer('grade')));
    }

    public function known(VerbsRequest $request, VerbReviewService $verbs): JsonResponse
    {
        return $this->each($request, $verbs, fn (User $user, string $v1) => $verbs->markKnown($user, $v1));
    }

    public function learn(VerbsRequest $request, VerbReviewService $verbs): JsonResponse
    {
        return $this->each($request, $verbs, fn (User $user, string $v1) => $verbs->learn($user, $v1));
    }

    public function forget(VerbsRequest $request, VerbReviewService $verbs): JsonResponse
    {
        return $this->each($request, $verbs, fn (User $user, string $v1) => $verbs->forget($user, $v1));
    }

    private function each(VerbsRequest $request, VerbReviewService $verbs, Closure $rule): JsonResponse
    {
        $user = $this->learner($request);

        DB::transaction(function () use ($request, $user, $rule): void {
            foreach ($request->verbs() as $v1) {
                $rule($user, $v1);
            }
        });

        return $this->changed($request, [
            'verbs' => VerbResource::collection(array_map(
                fn (string $v1): array => $verbs->withState($user, (array) $this->content->verb($v1)),
                $request->verbs(),
            )),
        ]);
    }
}
