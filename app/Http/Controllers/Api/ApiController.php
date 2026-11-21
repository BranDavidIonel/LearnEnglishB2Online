<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\HistoryService;
use App\Services\Progress\LearnerSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The progress API (routes/web/api.php): JSON in, JSON out, for the signed-in learner.
 * Every answer carries the fresh "learner" summary next to what changed, and every change
 * refreshes today's point of the progress chart (what the original "save" did).
 */
abstract class ApiController extends Controller
{
    public function __construct(
        protected readonly ContentRepository $content,
        private readonly HistoryService $history,
        private readonly LearnerSummary $summary,
    ) {}

    /** @param  array<string, mixed>  $data */
    protected function changed(Request $request, array $data = []): JsonResponse
    {
        $user = $this->learner($request);
        $this->history->snapshot($user);

        return response()->json([...$data, 'learner' => $this->summary->for($user)]);
    }

    protected function learner(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    /** A unit id of the URL that is not in the course is a 404. */
    protected function ensureUnit(string $unitId): void
    {
        abort_if($this->content->unitOutline($unitId) === null, 404);
    }
}
