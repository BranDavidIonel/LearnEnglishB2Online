<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Progress\ProgressExporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Writes a learner's progress in the format of the original app's progress.json
 * (a backup, or a file the original app can open). Without --output it goes to the screen.
 */
#[Signature('progress:export {--email= : The account (default: the admin of config/learning.php)} {--output= : The file to write}')]
#[Description('Write a learner\'s progress as a progress.json of the original app')]
class ExportProgress extends Command
{
    public function handle(ProgressExporter $exporter): int
    {
        $email = (string) ($this->option('email') ?: config('learning.admin.email'));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No account with the email {$email}.");

            return self::FAILURE;
        }

        $json = $exporter->toJson($user);
        $output = $this->option('output');

        if (! is_string($output) || $output === '') {
            $this->output->write($json);

            return self::SUCCESS;
        }

        file_put_contents($output, $json);
        $this->info("Progress of {$email} written to {$output}.");

        return self::SUCCESS;
    }
}
