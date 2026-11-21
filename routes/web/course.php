<?php

use App\Enums\Level;
use App\Enums\UnitStep;
use App\Http\Controllers\PlaceholderController;
use Illuminate\Support\Facades\Route;

// A unit and its steps, the level exams, the placement test and the mistakes to review.
Route::get('units/{unit}', PlaceholderController::class)->where('unit', '[a-z][0-9]-u[0-9]{2}')->name('units.show');
Route::get('units/{unit}/{step}', PlaceholderController::class)
    ->where('unit', '[a-z][0-9]-u[0-9]{2}')
    ->whereIn('step', UnitStep::values())
    ->name('units.step');
Route::get('exams/{level}', PlaceholderController::class)->whereIn('level', Level::values())->name('exams.show');
Route::get('placement', PlaceholderController::class)->name('placement');
Route::get('mistakes', PlaceholderController::class)->name('mistakes');
