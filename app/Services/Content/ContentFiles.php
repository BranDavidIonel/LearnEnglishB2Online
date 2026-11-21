<?php

namespace App\Services\Content;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Reads the JSON files of the course (config('learning.content_path')) the way the original
 * server did: a file that is missing or not valid JSON is simply left out.
 */
class ContentFiles
{
    public function __construct(private readonly string $root) {}

    public function root(): string
    {
        return $this->root;
    }

    /**
     * A JSON file of the content as an array, or null when it is missing or broken.
     *
     * @return array<mixed>|null
     */
    public function json(string $relativePath): ?array
    {
        $path = $this->root.'/'.$relativePath;

        if (! is_file($path)) {
            return null;
        }

        $text = (string) file_get_contents($path);
        // Some editors write a byte order mark at the start of the file.
        $data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text, true);

        return is_array($data) ? $data : null;
    }

    /**
     * The files of a folder whose name matches the pattern, sorted by name.
     *
     * @return list<string> names relative to the content root ("a1/unit01.json")
     */
    public function list(string $folder, string $pattern): array
    {
        $names = array_map('basename', glob($this->root.'/'.$folder.'/*.json') ?: []);
        $names = array_values(array_filter($names, fn (string $name): bool => preg_match($pattern, $name) === 1));
        sort($names);

        return array_map(fn (string $name): string => $folder.'/'.$name, $names);
    }

    /**
     * Changes whenever a content file is added, removed or edited: the cache keys carry it,
     * so a change of the content is picked up without clearing anything by hand.
     */
    public function fingerprint(): string
    {
        if (! is_dir($this->root)) {
            return 'none';
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS));
        $parts = [];

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() === 'json') {
                $parts[] = $file->getPathname().'|'.$file->getMTime().'|'.$file->getSize();
            }
        }

        sort($parts);

        return md5(implode("\n", $parts));
    }
}
