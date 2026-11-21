<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Progress\LearnerSummary;
use App\Services\Progress\ProgressImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Brings the progress of the original app (its user/progress.json) into the database.
 * It replaces everything the user had; the file itself is only read.
 */
#[Signature('progress:import {file : A progress.json of the original app} {--email= : The account (default: the admin of config/learning.php)}')]
#[Description('Replace a learner\'s progress with a progress.json of the original app')]
class ImportProgress extends Command
{
    public function handle(ProgressImporter $importer, LearnerSummary $summary): int
    {
        $file = (string) $this->argument('file');
        $email = (string) ($this->option('email') ?: config('learning.admin.email'));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No account with the email {$email} (run \"php artisan db:seed\" first).");

            return self::FAILURE;
        }

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        try {
            $importer->importJson($user, (string) file_get_contents($file));
        } catch (ValidationException $exception) {
            $this->error('The file was not imported: '.implode(' ', $exception->validator->errors()->all()));

            return self::FAILURE;
        }

        $learner = $summary->for($user);
        $this->info("Progress imported for {$email}: {$learner['xp']} XP, a streak of {$learner['streak']} days, level ".($learner['level']['name'] ?? '-').'.');
        $this->line("Due today: {$learner['due']['words']} words, {$learner['due']['verbs']} verbs, {$learner['due']['mistakes']} mistakes.");

        return self::SUCCESS;
    }
}
