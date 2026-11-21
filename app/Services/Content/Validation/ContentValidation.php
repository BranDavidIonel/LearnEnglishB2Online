<?php

namespace App\Services\Content\Validation;

/** Checks the course content against its format and collects everything it finds in one report. */
class ContentValidation
{
    /**
     * @param  list<ContentKind>  $kinds  none means every kind
     * @param  string|null  $root  the content folder, config('learning.content_path') by default
     */
    public function validate(array $kinds = [], ?string $root = null): ValidationReport
    {
        $root = rtrim($root ?? (string) config('learning.content_path'), '/\\');
        $report = new ValidationReport;

        foreach ($kinds === [] ? ContentKind::cases() : $kinds as $kind) {
            $kind->validator($root)->validate($report);
        }

        return $report;
    }
}
