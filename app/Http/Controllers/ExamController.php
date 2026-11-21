<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Course\ExamPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The final exam of a level. */
class ExamController extends Controller
{
    public function __invoke(Request $request, string $level, ExamPage $page): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('exams/Show', $page->data($user, $level));
    }
}
