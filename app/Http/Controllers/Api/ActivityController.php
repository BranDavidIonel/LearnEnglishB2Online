<?php

namespace App\Http\Controllers\Api;

use App\Enums\CambridgePaper;
use App\Enums\Game;
use App\Enums\SpeakingGame;
use App\Http\Requests\Api\CambridgeDraftRequest;
use App\Http\Requests\Api\CambridgeResultRequest;
use App\Http\Requests\Api\ConversationRequest;
use App\Http\Requests\Api\MistakesRequest;
use App\Http\Requests\Api\RecordRequest;
use App\Http\Requests\Api\SpokenRequest;
use App\Http\Requests\Api\XpRequest;
use App\Services\Progress\CambridgeService;
use App\Services\Progress\GameService;
use App\Services\Progress\MistakeService;
use App\Services\Progress\SpeakingService;
use App\Services\Progress\XpService;
use Illuminate\Http\JsonResponse;

/** XP of activities, my mistakes, the speaking practice, the games and the Cambridge mocks. */
class ActivityController extends ApiController
{
    public function xp(XpRequest $request, XpService $xp): JsonResponse
    {
        $xp->award($this->learner($request), $request->integer('amount'));

        return $this->changed($request, ['xp' => $request->integer('amount')]);
    }

    public function mistakes(MistakesRequest $request, MistakeService $mistakes): JsonResponse
    {
        $user = $this->learner($request);
        $added = array_values(array_filter($request->mistakeKeys('add'), fn (string $key): bool => $mistakes->add($user, $key)));
        $cleared = array_values(array_filter($request->mistakeKeys('clear'), fn (string $key): bool => $mistakes->clear($user, $key)));

        return $this->changed($request, ['added' => $added, 'cleared' => $cleared, 'mistakes' => $mistakes->count($user)]);
    }

    public function spoken(SpokenRequest $request, SpeakingService $speaking): JsonResponse
    {
        $user = $this->learner($request);
        $xp = $speaking->countSpoken($user, $request->integer('count', 1), $request->integer('xp', (int) config('learning.xp.spoken')[0]));

        return $this->changed($request, ['xp' => $xp, 'said' => $speaking->state($user)['said']]);
    }

    public function conversation(ConversationRequest $request, SpeakingService $speaking): JsonResponse
    {
        $result = $speaking->recordConversation($this->learner($request), $request->string('scenario')->value(), $request->float('score'));

        return $this->changed($request, $result);
    }

    public function speakingRecord(RecordRequest $request, string $game, SpeakingService $speaking): JsonResponse
    {
        $user = $this->learner($request);
        $record = $speaking->record($user, SpeakingGame::from($game), $request->integer('value'));

        return $this->changed($request, ['record' => $record, 'best' => $speaking->state($user)['best']]);
    }

    public function gameRecord(RecordRequest $request, string $game, GameService $games): JsonResponse
    {
        $user = $this->learner($request);
        $record = $games->record($user, Game::from($game), $request->integer('value'));

        return $this->changed($request, ['record' => $record, 'records' => $games->records($user)]);
    }

    public function cambridge(CambridgeResultRequest $request, string $exam, string $test, string $paper, CambridgeService $cambridge): JsonResponse
    {
        $this->ensureTest($exam, $test);
        $user = $this->learner($request);
        $result = $cambridge->record($user, $exam, $test, CambridgePaper::from($paper), $request->result());

        return $this->changed($request, [...$result, 'readiness' => $cambridge->readiness($user, $exam)]);
    }

    public function draft(CambridgeDraftRequest $request, string $exam, string $test, CambridgeService $cambridge): JsonResponse
    {
        $this->ensureTest($exam, $test);
        $cambridge->saveDraft($this->learner($request), $exam, $test, $request->integer('task'), (string) $request->input('text', ''));

        return $this->changed($request, ['saved' => true]);
    }

    /** An exam or a test of the URL that is not in the content is a 404. */
    private function ensureTest(string $exam, string $test): void
    {
        $tests = array_column($this->content->cambridgeExam($exam)['tests'] ?? [], 'id');

        abort_unless(in_array($test, $tests, true), 404);
    }
}
