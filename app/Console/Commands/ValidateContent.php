<?php

namespace App\Console\Commands;

use App\Services\Content\Validation\ContentKind;
use App\Services\Content\Validation\ContentValidation;
use App\Services\Content\Validation\Issue;
use App\Services\Content\Validation\Severity;
use App\Services\Content\Validation\ValidationReport;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Checks the course content (resources/content) against its format in docs/content/.
 * Run it after every content edit: it fails on errors, so the checks and the deploy stop
 * before broken content reaches the learner.
 */
class ValidateContent extends Command
{
    protected $signature = 'content:validate
        {--only=* : Check one kind only: lessons, speaking, cambridge, verbs, glossary or words (repeat the option or separate with commas)}';

    protected $description = 'Validate the course content (units, word banks, tests, speaking, Cambridge mocks, irregular verbs, glossary, tapped words)';

    public function handle(ContentValidation $validation): int
    {
        $kinds = $this->kinds();

        if ($kinds === null) {
            $this->components->error('Unknown kind of content. Use: '.implode(', ', ContentKind::values()).'.');

            return self::INVALID;
        }

        $report = $validation->validate($kinds);

        $this->printIssues($report);
        $this->printSummary($report);

        return $report->hasErrors() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The kinds asked for with --only (none means all), or null when one is unknown.
     *
     * @return list<ContentKind>|null
     */
    private function kinds(): ?array
    {
        /** @var list<string> $options */
        $options = $this->option('only');
        $names = array_filter(array_map('trim', explode(',', implode(',', $options))), fn (string $name) => $name !== '');
        $kinds = [];

        foreach (array_unique($names) as $name) {
            $kind = ContentKind::tryFrom(strtolower($name));

            if ($kind === null) {
                return null;
            }

            $kinds[] = $kind;
        }

        return $kinds;
    }

    private function printIssues(ValidationReport $report): void
    {
        foreach ($report->byFile() as $file => $issues) {
            $this->newLine();
            $this->line('<options=bold>'.OutputFormatter::escape($file).'</>');

            foreach ($issues as $issue) {
                $this->line('  '.$this->label($issue).' '.OutputFormatter::escape($issue->message));
            }
        }
    }

    private function label(Issue $issue): string
    {
        return $issue->severity === Severity::Error ? '<fg=red>error  </>' : '<fg=yellow>warning</>';
    }

    private function printSummary(ValidationReport $report): void
    {
        $summary = sprintf(
            'Checked %d %s: %d %s, %d %s.',
            $report->fileCount(),
            $report->fileCount() === 1 ? 'file' : 'files',
            $report->errorCount(),
            $report->errorCount() === 1 ? 'error' : 'errors',
            $report->warningCount(),
            $report->warningCount() === 1 ? 'warning' : 'warnings',
        );

        $this->newLine();

        if ($report->hasErrors()) {
            $this->components->error($summary);
        } elseif ($report->warningCount() > 0) {
            $this->components->warn($summary);
        } else {
            $this->components->info($summary);
        }
    }
}
