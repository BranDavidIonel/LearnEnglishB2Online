<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\FlagRequest;
use App\Http\Requests\Api\GoalRequest;
use App\Http\Requests\Api\ImportProgressRequest;
use App\Http\Requests\Api\SettingsRequest;
use App\Services\Progress\ProgressExporter;
use App\Services\Progress\ProgressImporter;
use App\Services\Progress\ResetService;
use App\Services\Progress\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Settings, daily goal, flags, and the learner's whole progress: export, import, reset. */
class SettingsController extends ApiController
{
    public function update(SettingsRequest $request, SettingsService $settings): JsonResponse
    {
        return $this->changed($request, ['settings' => $settings->update($this->learner($request), $request->patch())]);
    }

    public function goal(GoalRequest $request, SettingsService $settings): JsonResponse
    {
        $settings->setGoal($this->learner($request), $request->integer('goal'));

        return $this->changed($request, ['goal' => $request->integer('goal')]);
    }

    public function flag(FlagRequest $request, SettingsService $settings): JsonResponse
    {
        $user = $this->learner($request);
        $settings->setFlag($user, $request->string('flag')->value(), $request->boolean('value'));

        return $this->changed($request, ['flags' => $settings->flags($user)]);
    }

    /** The progress as a progress.json file of the original app (same name as the original download). */
    public function export(Request $request, ProgressExporter $exporter): StreamedResponse
    {
        $json = $exporter->toJson($this->learner($request));

        return response()->streamDownload(fn () => print ($json), 'english-levels-progress.json', ['Content-Type' => 'application/json']);
    }

    public function import(ImportProgressRequest $request, ProgressImporter $importer, SettingsService $settings): JsonResponse
    {
        $user = $this->learner($request);
        $importer->importJson($user, $request->string('content')->value());

        return $this->changed($request, ['settings' => $settings->settings($user)]);
    }

    public function reset(Request $request, ResetService $reset, SettingsService $settings): JsonResponse
    {
        $user = $this->learner($request);
        $reset->reset($user);

        return $this->changed($request, ['settings' => $settings->settings($user)]);
    }
}
