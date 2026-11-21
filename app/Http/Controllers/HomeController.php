<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/** Start page. A placeholder for now: the real home page replaces it. */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home');
    }
}
