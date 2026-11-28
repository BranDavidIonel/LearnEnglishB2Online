<?php

use App\Services\Content\ContentFiles;
use App\Services\Content\ContentRepository;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\File;

/** A repository over a content folder of the test, with its own cache. */
function contentRepository(string $root, ?Repository $cache = null): ContentRepository
{
    return new ContentRepository(new ContentFiles($root), $cache ?? new Repository(new ArrayStore), 3600);
}

/** A tiny course: one unit (with a word bank and test questions) in A1, nothing else. */
function tinyCourse(): string
{
    $root = sys_get_temp_dir().'/content-repository-test-'.uniqid();
    File::ensureDirectoryExists("{$root}/a1");
    File::put("{$root}/a1/unit01.json", json_encode([
        'id' => 'a1-u01', 'order' => 1, 'title' => 'Hello', 'titleRo' => 'Salut', 'emoji' => '👋',
        'vocab' => [['en' => 'hello', 'ro' => 'salut'], ['en' => 'apple', 'ro' => 'măr']],
        'exercises' => [['type' => 'mc'], ['type' => 'match']],
    ]));
    File::put("{$root}/a1/bank01.json", json_encode(['unit' => 'a1-u01', 'words' => [['en' => 'pear', 'ro' => 'pară'], ['en' => 'hello', 'ro' => 'bună']]]));
    File::put("{$root}/a1/test01.json", "\xEF\xBB\xBF".json_encode(['unit' => 'a1-u01', 'questions' => [['type' => 'mc']]]));
    File::put("{$root}/a1/placement.json", '{ broken');

    return $root;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/content-repository-test-*') ?: [] as $root) {
        File::deleteDirectory($root);
    }
});

it('reads the real course', function () {
    $content = app(ContentRepository::class);

    expect($content->levels())->toHaveCount(4)
        ->and(array_column($content->levels(), 'id'))->toBe(['a1', 'a2', 'b1', 'b2'])
        ->and(array_map(fn (array $level): int => count($level['units']), $content->levels()))->toBe([10, 10, 10, 10])
        ->and(array_column($content->levels(), 'placement'))->toBe([12, 12, 12, 12])
        ->and($content->words())->toHaveCount(4596)
        ->and($content->wordLevels())->toHaveCount(4596)
        ->and($content->verbs())->toHaveCount(239)
        ->and($content->cambridge())->toHaveCount(2)
        ->and(array_column($content->cambridge(), 'id'))->toBe(['pet', 'fce'])
        ->and(array_map(fn (array $exam): int => count($exam['tests']), $content->cambridge()))->toBe([2, 2]);
});

it('keeps the shape of the content files, with what the original added', function () {
    $content = app(ContentRepository::class);
    $unit = $content->unit('a1-u01');

    expect($unit)->toHaveKeys(['id', 'title', 'titleRo', 'vocab', 'grammar', 'dialogue', 'reading', 'exercises', 'bank', 'testItems'])
        ->and($unit['levelId'])->toBe('a1')
        ->and($unit['index'])->toBe(0)
        ->and($unit['bank'])->not->toBeEmpty()
        ->and($unit['testItems'])->not->toBeEmpty()
        ->and($content->unit('b2-u10')['index'])->toBe(9)
        ->and($content->unit('z9-u99'))->toBeNull()
        ->and($content->units('a2'))->toHaveCount(10)
        ->and($content->placement('b1'))->toHaveCount(12)
        ->and($content->speaking('a1'))->toHaveKeys(['scenarios', 'phrases', 'questions'])
        ->and($content->verbs()[0])->toMatchArray(['v1' => 'be', 'rank' => 1, 'levelId' => 'a1'])
        ->and($content->verb('feel')['rank'])->toBeGreaterThan(1)
        ->and($content->cambridgeExam('pet'))->toHaveKeys(['name', 'level', 'tests'])
        ->and($content->cambridgeExam('ielts'))->toBeNull();

    $outline = $content->unitOutline('a1-u01');
    expect($outline['vocab'])->toBe(array_column($unit['vocab'], 'en'))
        ->and($outline['exercises'])->toBe(count($unit['exercises']))
        ->and($outline['testItems'])->toBe(count($unit['testItems']));
});

it('indexes every word once, the first occurrence winning, bank words flagged', function () {
    $content = app(ContentRepository::class);
    $first = $content->unit('a1-u01')['vocab'][0];
    $bankWord = collect($content->unit('a1-u01')['bank'])->first(fn (array $word) => $content->word($word['en'])['unit'] === 'a1-u01');

    expect($content->word($first['en']))->toMatchArray([...$first, 'unit' => 'a1-u01', 'levelId' => 'a1'])
        ->and($content->word($first['en']))->not->toHaveKey('bank')
        ->and($content->word($bankWord['en'])['bank'])->toBeTrue()
        ->and($content->wordLevels()[$first['en']])->toBe('a1');
});

it('follows the rules of the original loader on a small course', function () {
    $content = contentRepository(tinyCourse());

    expect($content->levels())->toHaveCount(4)
        ->and($content->level('a2')['units'])->toBe([])
        ->and($content->placement('a1'))->toBe([]) // broken file: left out
        ->and($content->speaking('a1'))->toBeNull()
        ->and($content->unit('a1-u01')['testItems'])->toHaveCount(1) // byte order mark ignored
        ->and(array_keys($content->words()))->toBe(['hello', 'apple', 'pear'])
        ->and($content->word('hello')['ro'])->toBe('salut') // the core word wins over the bank
        ->and($content->word('pear')['bank'])->toBeTrue()
        ->and($content->verbs())->toBe([])
        ->and($content->cambridge())->toBe([]);
});

it('builds the content once and serves it from the cache', function () {
    $root = tinyCourse();
    $cache = new Repository(new ArrayStore);
    contentRepository($root, $cache)->words();

    // A new request: the files are not read again (only looked at for the fingerprint).
    $files = Mockery::mock(ContentFiles::class, [$root])->makePartial();
    $files->shouldNotReceive('json');
    $again = new ContentRepository($files, $cache, 3600);

    expect($again->words())->toHaveCount(3)
        ->and($again->unit('a1-u01')['id'])->toBe('a1-u01')
        ->and($again->speaking('a1'))->toBeNull();
});

it('picks up a changed content file by itself', function () {
    $root = tinyCourse();
    $cache = new Repository(new ArrayStore);
    expect(contentRepository($root, $cache)->words())->toHaveCount(3);

    File::put("{$root}/a1/bank01.json", json_encode(['unit' => 'a1-u01', 'words' => [['en' => 'plum', 'ro' => 'prună']]]));

    expect(array_keys(contentRepository($root, $cache)->words()))->toBe(['hello', 'apple', 'plum']);
});

it('is emptied by cache:clear (part of optimize:clear)', function () {
    app(ContentRepository::class)->words();
    $key = 'content:'.(new ContentFiles(config('learning.content_path')))->fingerprint().':words';

    expect(cache()->has($key))->toBeTrue();

    $this->artisan('cache:clear')->assertSuccessful();

    expect(cache()->has($key))->toBeFalse();
});
