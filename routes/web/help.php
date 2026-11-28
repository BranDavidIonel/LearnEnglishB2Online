<?php

use App\Enums\HelpTab;
use App\Http\Controllers\HelpController;
use Illuminate\Support\Facades\Route;

// Help: the pronunciation signs, the daily plan and the advice.
Route::get('help/{tab?}', HelpController::class)->whereIn('tab', HelpTab::values())->name('help');
