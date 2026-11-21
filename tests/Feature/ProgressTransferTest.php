<?php

use App\Models\User;
use App\Services\Progress\LearnerSummary;
use App\Services\Progress\ProgressExporter;
use App\Services\Progress\ProgressImporter;
use Illuminate\Validation\ValidationException;

/**
 * A JSON text in a form that compares values only: keys sorted, objects marked as such
 * ({} stays different from []), key order ignored.
 */
function comparableJson(string $json): mixed
{
    $normalize = function (mixed $value) use (&$normalize): mixed {
        if ($value instanceof stdClass) {
            $object = (array) $value;
            ksort($object, SORT_STRING);

            return ['{}' => array_map($normalize, $object)];
        }

        return is_array($value) ? array_map($normalize, $value) : $value;
    };

    return $normalize(json_decode($json, flags: JSON_THROW_ON_ERROR));
}

function progressFixture(): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/progress.json'));
}

beforeEach(function () {
    $this->travelTo('2026-11-15 18:00');
    $this->user = User::factory()->create(['email' => config('learning.admin.email')]);
});

it('gives back the same progress file after an import', function () {
    app(ProgressImporter::class)->importJson($this->user, progressFixture());

    expect(comparableJson(app(ProgressExporter::class)->toJson($this->user)))->toBe(comparableJson(progressFixture()));
});

it('keeps words, verbs, units and mistakes that are no longer in the content', function () {
    app(ProgressImporter::class)->importJson($this->user, progressFixture());

    expect($this->user->wordReviews()->where('word', 'a word that left the course')->exists())->toBeTrue()
        ->and($this->user->mistakes()->where('key', 'z9-u99:1')->exists())->toBeTrue()
        ->and($this->user->wordReviews()->where('word', "it's up to you")->sole()->due_on->toDateString())->toBe('2026-11-15');
});

it('puts the learner where the file says', function () {
    app(ProgressImporter::class)->importJson($this->user, progressFixture());

    expect(app(LearnerSummary::class)->for($this->user))->toMatchArray([
        'xp' => 230,
        'xpToday' => 30,
        'streak' => 3,
        'goal' => 50,
        'level' => ['id' => 'b2', 'name' => 'B2', 'color' => '#fb923c'],
    ]);
});

it('replaces the progress the learner had', function () {
    $this->user->mistakes()->create(['key' => 'b2-u01:1']);
    $this->user->wordReviews()->create(['word' => 'negotiate', 'due_on' => '2026-11-15']);

    app(ProgressImporter::class)->importJson($this->user, progressFixture());

    expect($this->user->mistakes()->where('key', 'b2-u01:1')->exists())->toBeFalse()
        ->and($this->user->wordReviews()->where('word', 'negotiate')->exists())->toBeFalse();
});

it('refuses a file that is not a progress file and changes nothing', function (string $json) {
    $this->user->mistakes()->create(['key' => 'b2-u01:1']);

    expect(fn () => app(ProgressImporter::class)->importJson($this->user, $json))->toThrow(ValidationException::class)
        ->and($this->user->mistakes()->count())->toBe(1);
})->with([
    'not JSON' => ['hello'],
    'a list' => ['[1, 2]'],
    'a word without its box' => ['{"words": {"hello": {"due": 20000}}}'],
    'a day that is not a date' => ['{"days": {"yesterday": 10}}'],
    'XP as text' => ['{"xp": "lots"}'],
]);

it('starts from the defaults for the sections a file lacks', function () {
    app(ProgressImporter::class)->importJson($this->user, '{"v": 1, "xp": 12}');
    $export = app(ProgressExporter::class)->export($this->user);

    expect($export['xp'])->toBe(12)
        ->and($export['goal'])->toBe(50)
        ->and($export['settings'])->toBe(['theme' => 'dark', 'rate' => 0.9, 'voice' => null, 'sound' => true])
        ->and($export['placement'])->toBeNull()
        ->and(json_encode($export['words']))->toBe('{}');
});

it('imports and exports with the artisan commands', function () {
    $file = tempnam(sys_get_temp_dir(), 'progress');
    file_put_contents($file, progressFixture());

    $this->artisan('progress:import', ['file' => $file])
        ->expectsOutputToContain('Progress imported for '.config('learning.admin.email').': 230 XP')
        ->assertSuccessful();

    $this->artisan('progress:export', ['--output' => $file])->assertSuccessful();
    expect(comparableJson((string) file_get_contents($file)))->toBe(comparableJson(progressFixture()));

    $this->artisan('progress:import', ['file' => $file, '--email' => 'nobody@example.test'])->assertFailed();
    $this->artisan('progress:import', ['file' => $file.'.missing'])->assertFailed();

    unlink($file);
});
