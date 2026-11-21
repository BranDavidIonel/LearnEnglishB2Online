<?php

namespace App\Http\Controllers;

use App\Services\Home\CourseMapService;
use Inertia\Inertia;
use Inertia\Response;

/** The recommended videos of every unit, by level. */
class VideoController extends Controller
{
    public function __invoke(CourseMapService $map): Response
    {
        return Inertia::render('Videos', ['levels' => $map->videos()]);
    }
}
