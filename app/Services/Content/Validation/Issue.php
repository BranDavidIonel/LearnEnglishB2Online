<?php

namespace App\Services\Content\Validation;

/** One problem found in the content: the file (relative to the content folder), where and what. */
final readonly class Issue
{
    public function __construct(
        public string $file,
        public string $message,
        public Severity $severity,
    ) {}
}
