<?php

use App\Enums\Level;
use App\Enums\UnitStep;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\MistakeController;
use App\Http\Controllers\PlacementController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

// A unit and its steps, the level exams, the placement test and the mistakes to review.
Route::get('units/{unit}', [UnitController::class, 'show'])->where('unit', '[a-z][0-9]-u[0-9]{2}')->name('units.show');
Route::get('units/{unit}/{step}', [UnitController::class, 'step'])
    ->where('unit', '[a-z][0-9]-u[0-9]{2}')
    ->whereIn('step', UnitStep::values())
    ->name('units.step');
Route::get('exams/{level}', ExamController::class)->whereIn('level', Level::values())->name('exams.show');
Route::get('placement', PlacementController::class)->name('placement');
Route::get('mistakes', MistakeController::class)->name('mistakes');
