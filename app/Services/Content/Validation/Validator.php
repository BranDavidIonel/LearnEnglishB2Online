<?php

namespace App\Services\Content\Validation;

use JsonException;

/** One kind of course content checked against its format (docs/content/). */
abstract class Validator
{
    public function __construct(protected readonly string $root) {}

    abstract public function validate(ValidationReport $report): void;

    protected function exists(string $file): bool
    {
        return is_file($this->path($file));
    }

    protected function path(string $file): string
    {
        return $this->root.'/'.$file;
    }

    /**
     * Reads an existing file; broken JSON is reported and gives null.
     *
     * @return array<array-key, mixed>|null
     */
    protected function decode(string $file, ValidationReport $report): ?array
    {
        $report->checked($file);

        try {
            $data = json_decode((string) file_get_contents($this->path($file)), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $report->error($file, "invalid JSON: {$e->getMessage()}");

            return null;
        }

        return Value::fields($data);
    }

    /**
     * Reads a file without reporting anything, for the cross-file checks.
     *
     * @return array<array-key, mixed>|null
     */
    protected function peek(string $file): ?array
    {
        if (! $this->exists($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->path($file)), true);

        return is_array($data) ? $data : null;
    }
}
