<?php

/** The course content is plain JSON under config('learning.content_path'). */
it('has the course content as valid JSON files', function () {
    $path = config('learning.content_path');

    expect(is_file("{$path}/a1/unit01.json"))->toBeTrue()
        ->and(is_file("{$path}/verbs.json"))->toBeTrue()
        ->and(is_file("{$path}/cambridge/pet/test1.json"))->toBeTrue();

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        expect(json_decode(file_get_contents($file->getPathname()), flags: JSON_THROW_ON_ERROR))
            ->not->toBeNull($file->getPathname());
    }
});
