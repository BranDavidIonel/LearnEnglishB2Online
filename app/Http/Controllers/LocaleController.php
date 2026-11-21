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
        // Signed in: remembered for the next logins too.
        $request->user()?->update(['locale' => $validated['locale']]);

        return back();
    }
}
