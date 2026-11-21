<?php

use App\Http\Controllers\CambridgeController;
use Illuminate\Support\Facades\Route;

// Cambridge mock exams: the exams, one exam, one paper of one of its tests. The paper is
// not limited to the four names: like the original, an unknown one goes back to the exam.
Route::get('cambridge/{exam?}/{test?}/{paper?}', CambridgeController::class)
    ->where('exam', '[a-z0-9]+')
    ->where('test', '[a-z0-9-]+')
    ->where('paper', '[a-z]+')
    ->name('cambridge');
