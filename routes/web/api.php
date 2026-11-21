<?php

use App\Enums\CambridgePaper;
use App\Enums\Game;
use App\Enums\Level;
use App\Enums\SpeakingGame;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\VerbController;
use App\Http\Controllers\Api\WordController;
use Illuminate\Support\Facades\Route;

/*
 * The progress API: JSON for the pages (resources/js/api/progress.ts), session login and
 * CSRF like every page. Words and verbs travel in the body, never in the URL.
 * docs/backend.md lists every route with what it answers.
 */
Route::prefix('api')->name('api.')->group(function () {
    Route::post('units/{unit}/steps', [CourseController::class, 'step'])->where('unit', '[a-z][0-9]-u[0-9]{2}')->name('units.step');
    Route::post('units/{unit}/test', [CourseController::class, 'test'])->where('unit', '[a-z][0-9]-u[0-9]{2}')->name('units.test');
    Route::post('exams/{level}', [CourseController::class, 'exam'])->whereIn('level', Level::values())->name('exams.store');
    Route::post('placement', [CourseController::class, 'placement'])->name('placement.store');

    Route::post('words/grade', [WordController::class, 'grade'])->name('words.grade');
    Route::post('words/known', [WordController::class, 'known'])->name('words.known');
    Route::post('words/sorted', [WordController::class, 'sorted'])->name('words.sorted');
    Route::post('words/learn', [WordController::class, 'learn'])->name('words.learn');
    Route::post('words/forget', [WordController::class, 'forget'])->name('words.forget');

    Route::post('verbs/grade', [VerbController::class, 'grade'])->name('verbs.grade');
    Route::post('verbs/known', [VerbController::class, 'known'])->name('verbs.known');
    Route::post('verbs/learn', [VerbController::class, 'learn'])->name('verbs.learn');
    Route::post('verbs/forget', [VerbController::class, 'forget'])->name('verbs.forget');

    Route::post('xp', [ActivityController::class, 'xp'])->name('xp.store');
    Route::post('mistakes', [ActivityController::class, 'mistakes'])->name('mistakes.update');
    Route::post('speaking/spoken', [ActivityController::class, 'spoken'])->name('speaking.spoken');
    Route::post('speaking/conversations', [ActivityController::class, 'conversation'])->name('speaking.conversation');
    Route::post('speaking/records/{game}', [ActivityController::class, 'speakingRecord'])
        ->whereIn('game', array_column(SpeakingGame::cases(), 'value'))
        ->name('speaking.record');
    Route::post('games/{game}/record', [ActivityController::class, 'gameRecord'])->whereIn('game', Game::values())->name('games.record');
    Route::put('cambridge/{exam}/{test}/drafts', [ActivityController::class, 'draft'])
        ->where(['exam' => '[a-z0-9]+', 'test' => '[a-z0-9-]+'])
        ->name('cambridge.draft');
    Route::post('cambridge/{exam}/{test}/{paper}', [ActivityController::class, 'cambridge'])
        ->where(['exam' => '[a-z0-9]+', 'test' => '[a-z0-9-]+'])
        ->whereIn('paper', CambridgePaper::values())
        ->name('cambridge.store');

    Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('goal', [SettingsController::class, 'goal'])->name('goal.update');
    Route::put('flags', [SettingsController::class, 'flag'])->name('flags.update');
    Route::get('progress/export', [SettingsController::class, 'export'])->name('progress.export');
    Route::post('progress/import', [SettingsController::class, 'import'])->name('progress.import');
    Route::delete('progress', [SettingsController::class, 'reset'])->name('progress.destroy');
});
