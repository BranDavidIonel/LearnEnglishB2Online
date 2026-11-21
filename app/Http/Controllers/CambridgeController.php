<?php

namespace App\Http\Controllers;

use App\Enums\CambridgePaper;
use App\Models\User;
use App\Services\Cambridge\CambridgePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Cambridge mock exams: the list of exams, one exam, and one paper of one of its
 * tests. As in the original, an exam that is not in the content shows "exam not
 * available", and a test or paper that does not exist sends the learner back to the exam.
 */
class CambridgeController extends Controller
{
    public function __construct(private readonly CambridgePages $pages) {}

    public function __invoke(Request $request, ?string $exam = null, ?string $test = null, ?string $paper = null): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($exam === null) {
            return Inertia::render('cambridge/Index', $this->pages->index($user));
        }

        $examData = $this->pages->findExam($exam);

        if ($examData === null) {
            return Inertia::render('cambridge/Unavailable');
        }

        if ($test === null) {
            return Inertia::render('cambridge/Exam', $this->pages->exam($user, $examData));
        }

        $testData = $this->pages->findTest($examData, $test);
        $paperCase = CambridgePaper::tryFrom((string) $paper);

        if ($testData === null || $paperCase === null) {
            return redirect()->route('cambridge', ['exam' => $exam]);
        }

        return match ($paperCase) {
            CambridgePaper::Writing => Inertia::render('cambridge/Writing', $this->pages->writing($user, $examData, $testData)),
            CambridgePaper::Speaking => Inertia::render('cambridge/Speaking', $this->pages->paper($examData, $testData, $paperCase)),
            default => Inertia::render('cambridge/Paper', $this->pages->paper($examData, $testData, $paperCase)),
        };
    }
}
