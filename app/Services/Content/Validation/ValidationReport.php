<?php

namespace App\Services\Content\Validation;

/** What the validators found: every issue in the order it was found, and how many files were read. */
final class ValidationReport
{
    /** @var list<Issue> */
    private array $issues = [];

    /** @var array<string, true> */
    private array $files = [];

    public function error(string $file, string $message): void
    {
        $this->issues[] = new Issue($file, $message, Severity::Error);
    }

    public function warning(string $file, string $message): void
    {
        $this->issues[] = new Issue($file, $message, Severity::Warning);
    }

    /** Remembers a file that was read, for the summary line. */
    public function checked(string $file): void
    {
        $this->files[$file] = true;
    }

    /** @return list<Issue> */
    public function issues(): array
    {
        return $this->issues;
    }

    /**
     * The issues grouped by file, files in the order they were first reported.
     *
     * @return array<string, list<Issue>>
     */
    public function byFile(): array
    {
        $groups = [];

        foreach ($this->issues as $issue) {
            $groups[$issue->file][] = $issue;
        }

        return $groups;
    }

    public function fileCount(): int
    {
        return count($this->files);
    }

    public function errorCount(): int
    {
        return $this->count(Severity::Error);
    }

    public function warningCount(): int
    {
        return $this->count(Severity::Warning);
    }

    public function hasErrors(): bool
    {
        return $this->errorCount() > 0;
    }

    private function count(Severity $severity): int
    {
        return count(array_filter($this->issues, fn (Issue $issue) => $issue->severity === $severity));
    }
}
