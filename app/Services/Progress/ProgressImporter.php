<?php

namespace App\Services\Progress;

use App\Enums\Theme;
use App\Models\CambridgeTest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Reads a progress.json of the original app (or one written by ProgressExporter) and
 * replaces the learner's progress with it. Everything is kept as it is in the file, also
 * words, verbs and units that are no longer in the content; review day numbers become
 * dates. A file that is not a progress file is refused (ValidationException) and nothing
 * changes.
 */
class ProgressImporter
{
    /** Sections of the file and the rules of their values. */
    private const array RULES = [
        'xp' => ['sometimes', 'integer', 'min:0'],
        'goal' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        'streak' => ['sometimes', 'array'],
        'streak.count' => ['sometimes', 'integer', 'min:0'],
        'streak.last' => ['nullable', 'date_format:Y-m-d'],
        'days' => ['sometimes', 'array'],
        'days.*' => ['integer'],
        'placement' => ['nullable', 'array'],
        'placement.start' => ['required_with:placement', 'integer', 'min:0', 'max:255'],
        'placement.scores' => ['sometimes', 'array'],
        'placement.scores.*' => ['numeric'],
        'placement.date' => ['nullable', 'date_format:Y-m-d'],
        'units' => ['sometimes', 'array'],
        'units.*' => ['array'],
        'units.*.steps' => ['sometimes', 'array'],
        'units.*.best' => ['sometimes', 'numeric'],
        'units.*.passed' => ['sometimes', 'boolean'],
        'units.*.review' => ['sometimes', 'boolean'],
        'units.*.tries' => ['sometimes', 'integer', 'min:0'],
        'exams' => ['sometimes', 'array'],
        'exams.*' => ['array'],
        'exams.*.best' => ['sometimes', 'numeric'],
        'exams.*.passed' => ['sometimes', 'boolean'],
        'exams.*.tries' => ['sometimes', 'integer', 'min:0'],
        'exams.*.last' => ['nullable', 'date_format:Y-m-d'],
        'words' => ['sometimes', 'array'],
        'words.*' => ['array'],
        'words.*.box' => ['required', 'integer', 'min:0', 'max:255'],
        'words.*.due' => ['required', 'integer'],
        'words.*.ok' => ['sometimes', 'integer', 'min:0'],
        'words.*.bad' => ['sometimes', 'integer', 'min:0'],
        'words.*.known' => ['sometimes', 'boolean'],
        'verbs' => ['sometimes', 'array'],
        'verbs.*' => ['array'],
        'verbs.*.box' => ['required', 'integer', 'min:0', 'max:255'],
        'verbs.*.due' => ['required', 'integer'],
        'verbs.*.ok' => ['sometimes', 'integer', 'min:0'],
        'verbs.*.bad' => ['sometimes', 'integer', 'min:0'],
        'verbs.*.known' => ['sometimes', 'boolean'],
        'mistakes' => ['sometimes', 'array'],
        'mistakes.*' => ['string', 'max:40'],
        'games' => ['sometimes', 'array'],
        'games.*' => ['integer'],
        'settings' => ['sometimes', 'array'],
        'settings.rate' => ['sometimes', 'numeric'],
        'settings.voice' => ['nullable', 'string', 'max:255'],
        'settings.sound' => ['sometimes', 'boolean'],
        'history' => ['sometimes', 'array'],
        'history.*' => ['array'],
        'history.*.xp' => ['sometimes', 'integer', 'min:0'],
        'history.*.w' => ['sometimes', 'integer', 'min:0'],
        'history.*.v' => ['sometimes', 'integer', 'min:0'],
        'history.*.b1' => ['sometimes', 'numeric'],
        'history.*.b2' => ['sometimes', 'numeric'],
        'cambridge' => ['sometimes', 'array'],
        'cambridge.*' => ['array'],
        'cambridge.*.*' => ['array'],
        'speak' => ['sometimes', 'array'],
        'speak.said' => ['sometimes', 'integer', 'min:0'],
        'speak.scen' => ['sometimes', 'array'],
        'speak.scen.*' => ['numeric'],
        'speak.best' => ['sometimes', 'array'],
        'speak.best.*' => ['integer'],
        'flags' => ['sometimes', 'array'],
    ];

    public function __construct(
        private readonly LearnerState $state,
        private readonly ResetService $reset,
    ) {}

    /**
     * Replaces the learner's progress with the text of a progress.json file.
     *
     * @throws ValidationException
     */
    public function importJson(User $user, string $json): void
    {
        $data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $json) ?? $json, true);

        if (! is_array($data) || array_is_list($data)) {
            throw ValidationException::withMessages(['content' => __('This is not a progress file of English Levels.')]);
        }

        $this->import($user, $data);
    }

    /**
     * Replaces the learner's progress with the content of a progress.json file.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function import(User $user, array $data): void
    {
        Validator::make($data, self::RULES)->validate();
        $this->checkDays($data);

        DB::transaction(function () use ($user, $data): void {
            $this->reset->reset($user);
            $this->profile($user, $data);
            $this->days($user, $data);
            $this->course($user, $data);
            $this->reviews($user, $data);
            $this->activities($user, $data);
        });

        $this->state->forget($user);
    }

    /** @param  array<string, mixed>  $data */
    private function profile(User $user, array $data): void
    {
        $settings = $data['settings'] ?? [];

        $user->profile()->create([
            'xp' => $data['xp'] ?? 0,
            'goal' => $data['goal'] ?? (int) config('learning.rules.goal'),
            'streak_count' => $data['streak']['count'] ?? 0,
            'streak_last' => $data['streak']['last'] ?? null,
            // An unknown theme falls back to the dark one, as in the original.
            'theme' => Theme::tryFrom((string) ($settings['theme'] ?? '')) ?? Theme::Dark,
            'speech_rate' => $settings['rate'] ?? (float) config('learning.rules.rate.default'),
            'voice' => $settings['voice'] ?? null,
            'sound' => (bool) ($settings['sound'] ?? true),
            'spoken' => $data['speak']['said'] ?? 0,
            'flags' => $data['flags'] ?? [],
        ]);

        if (isset($data['placement'])) {
            $user->placement()->create([
                'start_index' => $data['placement']['start'],
                'scores' => $data['placement']['scores'] ?? [],
                'taken_on' => $data['placement']['date'] ?? null,
            ]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function days(User $user, array $data): void
    {
        foreach ($data['days'] ?? [] as $day => $xp) {
            $user->dailyXp()->create(['day' => $day, 'xp' => $xp]);
        }

        foreach ($data['history'] ?? [] as $day => $snapshot) {
            $user->snapshots()->create([
                'day' => $day,
                'xp' => $snapshot['xp'] ?? 0,
                'words' => $snapshot['w'] ?? null,
                'verbs' => $snapshot['v'] ?? null,
                'b1' => $snapshot['b1'] ?? null,
                'b2' => $snapshot['b2'] ?? null,
            ]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function course(User $user, array $data): void
    {
        foreach ($data['units'] ?? [] as $unit => $state) {
            $user->unitProgress()->create([
                'unit' => (string) $unit,
                // The original kept the steps as {"vocab": true, …}.
                'steps' => array_map('strval', array_keys(array_filter($state['steps'] ?? []))),
                'best' => $state['best'] ?? 0,
                'passed' => (bool) ($state['passed'] ?? false),
                'review' => (bool) ($state['review'] ?? false),
                'tries' => $state['tries'] ?? 0,
            ]);
        }

        foreach ($data['exams'] ?? [] as $level => $exam) {
            $user->examResults()->create([
                'level' => (string) $level,
                'best' => $exam['best'] ?? 0,
                'passed' => (bool) ($exam['passed'] ?? false),
                'tries' => $exam['tries'] ?? 0,
                'last_taken_on' => $exam['last'] ?? null,
            ]);
        }

        foreach (array_values(array_unique($data['mistakes'] ?? [])) as $key) {
            $user->mistakes()->create(['key' => $key]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function reviews(User $user, array $data): void
    {
        foreach (['words' => 'word', 'verbs' => 'verb'] as $section => $column) {
            $rows = [];

            foreach ($data[$section] ?? [] as $key => $review) {
                $rows[] = [
                    'user_id' => $user->id,
                    $column => (string) $key,
                    'box' => $review['box'],
                    'due_on' => Calendar::fromDayNumber((int) $review['due']),
                    'ok' => $review['ok'] ?? 0,
                    'bad' => $review['bad'] ?? 0,
                    'known' => (bool) ($review['known'] ?? false),
                ];
            }

            // In the order of the file (the id keeps it), a few hundred rows at a time.
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table("{$column}_reviews")->insert($chunk);
            }
        }
    }

    /** @param  array<string, mixed>  $data */
    private function activities(User $user, array $data): void
    {
        foreach ($data['games'] ?? [] as $game => $value) {
            $user->bestScores()->create(['area' => 'games', 'game' => (string) $game, 'value' => $value]);
        }

        foreach ($data['speak']['best'] ?? [] as $game => $value) {
            $user->bestScores()->create(['area' => 'speaking', 'game' => (string) $game, 'value' => $value]);
        }

        foreach ($data['speak']['scen'] ?? [] as $scenario => $score) {
            $user->speakingScores()->create(['scenario' => (string) $scenario, 'score' => $score]);
        }

        foreach ($data['cambridge'] ?? [] as $exam => $tests) {
            foreach ($tests as $test => $state) {
                $this->cambridgeTest($user, (string) $exam, (string) $test, $state);
            }
        }
    }

    /** @param  array<string, mixed>  $state  {reading: {…, date}, …, drafts: {…}} */
    private function cambridgeTest(User $user, string $exam, string $test, array $state): void
    {
        $row = $user->cambridgeTests()->create([
            'exam' => $exam,
            'test' => $test,
            'drafts' => is_array($state['drafts'] ?? null) ? $state['drafts'] : null,
        ]);

        foreach ($state as $paper => $result) {
            if ($paper !== 'drafts' && is_array($result)) {
                $this->cambridgeResult($user, $row, (string) $paper, $result);
            }
        }
    }

    /** @param  array<string, mixed>  $result */
    private function cambridgeResult(User $user, CambridgeTest $test, string $paper, array $result): void
    {
        $date = $result['date'] ?? null;
        unset($result['date']);

        $test->results()->create([
            'user_id' => $user->id,
            'paper' => $paper,
            'result' => $result,
            'taken_on' => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
        ]);
    }

    /**
     * The keys of "days" and "history" must be days ("2026-11-21").
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function checkDays(array $data): void
    {
        foreach (['days', 'history'] as $section) {
            foreach (array_keys($data[$section] ?? []) as $day) {
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $day)) {
                    throw ValidationException::withMessages([$section => __('This is not a progress file of English Levels.')]);
                }
            }
        }
    }
}
