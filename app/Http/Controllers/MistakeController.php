<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Course\MistakesPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "My mistakes": the questions answered wrong, to practise until they are right. */
class MistakeController extends Controller
{
    public function __invoke(Request $request, MistakesPage $page): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Mistakes', $page->data($user));
    }
}
