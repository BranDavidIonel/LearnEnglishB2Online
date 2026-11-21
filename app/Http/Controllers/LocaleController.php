<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Language switch (EN / RO), also on the login page. */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(config('learning.locales'))],
        ]);

        $request->session()->put(SetLocale::SESSION, $validated['locale']);

        return back();
    }
}
