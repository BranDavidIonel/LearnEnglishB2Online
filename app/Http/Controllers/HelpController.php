<?php

namespace App\Http\Controllers;

use App\Enums\HelpTab;
use App\Models\User;
use App\Services\Help\HelpPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Help: how to read the pronunciation signs written under every word, what to do every day
 * and what helps besides the app. A tab only swaps the "tab" prop.
 */
class HelpController extends Controller
{
    public function __invoke(Request $request, HelpPage $page, ?string $tab = null): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Help', [
            'tab' => $tab ?? HelpTab::Sounds->value,
            'sounds' => fn (): array => $page->sounds(),
            'plan' => fn (): array => $page->plan($user),
            'tips' => fn (): array => $page->tips(),
        ]);
    }
}
