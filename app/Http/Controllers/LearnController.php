<?php

namespace App\Http\Controllers;

use App\Http\Resources\Home\LearnLevelResource;
use App\Models\User;
use App\Services\Home\CourseMapService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The learning path: every level with its units, their locks and progress, and the level exams. */
class LearnController extends Controller
{
    public function __invoke(Request $request, CourseMapService $map): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Learn', [
            'levels' => LearnLevelResource::collection($map->learnLevels($user)),
            'batch' => (int) config('learning.rules.word_batch'),
            'passMark' => (float) config('learning.rules.pass'),
        ]);
    }
}
