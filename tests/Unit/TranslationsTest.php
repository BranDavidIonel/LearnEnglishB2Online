<?php

/**
 * Every UI text used in the code ($t('...') in Vue/TS, __('...') in PHP/Blade) must have
 * a Romanian translation, with the same :placeholders. The Romanian dictionary is
 * lang/ro.json plus every resources/js/i18n/ro/*.json file (merged by resources/js/i18n).
 */

/** @return array<string, array<string, string>> file => its texts */
function romanianFiles(): array
{
    $root = dirname(__DIR__, 2);
    $files = ["{$root}/lang/ro.json", ...(glob("{$root}/resources/js/i18n/ro/*.json") ?: [])];
    $dictionaries = [];

    foreach ($files as $file) {
        $dictionaries[substr($file, strlen($root) + 1)] = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    }

    return $dictionaries;
}

/** @return array<string, list<string>> text => files that use it */
function usedTranslationKeys(): array
{
    $root = dirname(__DIR__, 2);
    $patterns = [];

    // $t('…'), __('…') and trans('…'), in single or double quotes (a text with an apostrophe
    // is written in double quotes by the formatter).
    foreach (['\\$t', '__', 'trans'] as $call) {
        foreach (["'", '"'] as $quote) {
            $patterns[] = "/{$call}\\(\\s*{$quote}((?:[^{$quote}\\\\]|\\\\.)*){$quote}/";
        }
    }

    $keys = [];

    foreach (['resources/js', 'app', 'resources/views'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            // Generated Wayfinder files and the translator itself are not UI texts.
            if (! preg_match('/\.(vue|ts|php)$/', $path) || preg_match('#/(routes|actions|wayfinder|i18n)/#', $path)) {
                continue;
            }

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, file_get_contents($path), $matches);

                foreach ($matches[1] as $key) {
                    $keys[stripslashes($key)][] = substr($path, strlen($root) + 1);
                }
            }
        }
    }

    return $keys;
}

function placeholders(string $text): array
{
    preg_match_all('/:([a-z_]+)/i', $text, $matches);
    $names = array_unique($matches[1]);
    sort($names);

    return $names;
}

it('has a Romanian translation for every UI text', function () {
    $romanian = array_merge(...array_values(romanianFiles()));

    $used = usedTranslationKeys();
    $missing = array_diff_key($used, $romanian);

    expect(count($used))->toBeGreaterThan(5, 'The key scanner found too few texts; check the regex.')
        ->and($missing)->toBeEmpty('Missing in the Romanian dictionary: '.implode(' | ', array_keys($missing)));
});

it('keeps every Romanian text in one dictionary file only', function () {
    $seen = [];
    $duplicates = [];

    foreach (romanianFiles() as $file => $texts) {
        foreach (array_keys($texts) as $key) {
            if (isset($seen[$key])) {
                $duplicates[] = "{$key} ({$seen[$key]}, {$file})";
            }

            $seen[$key] = $file;
        }
    }

    expect($duplicates)->toBeEmpty('In more than one file: '.implode(' | ', $duplicates));
});

it('keeps the same placeholders in the Romanian texts', function () {
    $romanian = array_merge(...array_values(romanianFiles()));

    foreach (array_keys(usedTranslationKeys()) as $key) {
        if (isset($romanian[$key])) {
            expect(placeholders($romanian[$key]))->toBe(placeholders($key), "Placeholders differ for: {$key}");
        }
    }
});
