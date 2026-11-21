<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CompleteStepRequest;
use App\Http\Requests\Api\ExamRequest;
use App\Http\Requests\Api\PlacementRequest;
use App\Http\Requests\Api\ScoreRequest;
use App\Http\Resources\LevelResource;
use App\Http\Resources\UnitResource;
use App\Models\User;
use App\Services\Progress\CourseProgressService;
use App\Services\Progress\ExamService;
use App\Services\Progress\PlacementService;
use App\Services\Progress\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** The course: lesson steps, unit tests, level exams and the placement test. */
class CourseController extends ApiController
{
    public function step(CompleteStepRequest $request, string $unit, UnitService $units, CourseProgressService $course): JsonResponse
    {
        $this->ensureUnit($unit);
        $user = $this->learner($request);
        $result = $units->completeStep($user, $unit, $request->step());

        return $this->changed($request, [
            'first' => $result['first'],
            'xp' => $result['xp'],
            'unit' => new UnitResource($course->unitSummary($user, $unit)),
        ]);
    }

    public function test(ScoreRequest $request, string $unit, UnitService $units, CourseProgressService $course): JsonResponse
    {
        $this->ensureUnit($unit);
        $user = $this->learner($request);
        $result = $units->recordUnitTest($user, $unit, $request->score());
        $outline = $this->content->unitOutline($unit);
        $next = $this->content->level((string) $outline['levelId'])['units'][$outline['index'] + 1]['id'] ?? null;

        return $this->changed($request, [
            'passed' => $result['passed'],
            'first' => $result['first'],
            'xp' => $result['xp'],
            'unit' => new UnitResource($course->unitSummary($user, $unit)),
            'next' => $next === null ? null : new UnitResource($course->unitSummary($user, $next)),
            'review' => $course->reviewUnits($user, (string) $outline['levelId']),
        ]);
    }

    public function exam(ExamRequest $request, string $level, ExamService $exams, CourseProgressService $course): JsonResponse
    {
        $user = $this->learner($request);
        $result = $exams->recordExam($user, $level, $request->results());
        $next = $this->content->levels()[$this->content->level($level)['index'] + 1]['id'] ?? null;

        return $this->changed($request, [
            ...$result,
            'level' => new LevelResource($course->levelSummary($user, $level)),
            'next' => $next === null ? null : new LevelResource($course->levelSummary($user, $next)),
            'units' => $this->units($user, $level, $course),
        ]);
    }

    public function placement(PlacementRequest $request, PlacementService $placement, CourseProgressService $course): JsonResponse
    {
        $user = $this->learner($request);
        $result = $placement->recordPlacement($user, $request->scores());

        return $this->changed($request, [
            'start' => $result['start'],
            'level' => $result['level'],
            'xp' => $result['xp'],
            'placement' => $placement->placement($user),
            'levels' => LevelResource::collection(array_map(
                fn (array $level): ?array => $course->levelSummary($user, $level['id']),
                $this->content->levels(),
            )),
        ]);
    }

    private function units(User $user, string $level, CourseProgressService $course): AnonymousResourceCollection
    {
        return UnitResource::collection(array_map(
            fn (array $unit): ?array => $course->unitSummary($user, $unit['id']),
            $this->content->level($level)['units'] ?? [],
        ));
    }
}
