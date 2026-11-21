<?php

namespace App\Services\Content\Validation;

/**
 * The irregular verbs (verbs.json, docs/content/SCHEMA.md): every verb once, its examples
 * really use the form they teach, and the Romanian is written with comma-below ș and ț.
 */
final class VerbValidator extends Validator
{
    public const string FILE = 'verbs.json';

    private const array FIELDS = ['v1', 'v2', 'v3', 'ro', 'ipa', 'ex2', 'ex2Ro', 'ex3', 'ex3Ro'];

    private const array LEVELS = ['A1', 'A2', 'B1', 'B2'];

    public function validate(ValidationReport $report): void
    {
        if (! $this->exists(self::FILE)) {
            $report->warning(self::FILE, 'file missing (no irregular verbs section)');

            return;
        }

        $data = $this->decode(self::FILE, $report);

        if ($data === null) {
            return;
        }

        $verbs = $data['verbs'] ?? null;

        if (! Value::isList($verbs) || $verbs === []) {
            $report->error(self::FILE, 'needs a verbs[] array');

            return;
        }

        $seen = [];

        foreach ($verbs as $i => $verb) {
            $verb = Value::fields($verb);
            $v1 = $verb['v1'] ?? null;
            $where = "verbs[{$i}] (".Value::show($v1).')';

            $this->verb($report, $where, $verb);

            $key = Value::show($v1);

            if (isset($seen[$key])) {
                $report->error(self::FILE, "{$where} repeated");
            }

            $seen[$key] = true;

            $this->examples($report, $where, $verb);
        }
    }

    /** @param  array<array-key, mixed>  $verb */
    private function verb(ValidationReport $report, string $where, array $verb): void
    {
        foreach (self::FIELDS as $key) {
            if (! Value::filled($verb[$key] ?? null)) {
                $report->error(self::FILE, "{$where} missing {$key}");
            }
        }

        $level = $verb['level'] ?? null;

        if (! in_array($level, self::LEVELS, true)) {
            $report->error(self::FILE, "{$where} bad level \"".Value::show($level).'"');
        }
    }

    /** @param  array<array-key, mixed>  $verb */
    private function examples(ValidationReport $report, string $where, array $verb): void
    {
        $v2 = Value::show($verb['v2'] ?? null);
        $v3 = Value::show($verb['v3'] ?? null);

        if (Value::filled($verb['ex2'] ?? null) && ! $this->uses($verb['ex2'], $v2)) {
            $report->error(self::FILE, "{$where} ex2 must use the past simple \"{$v2}\"");
        }

        if (Value::filled($verb['ex3'] ?? null) && ! $this->uses($verb['ex3'], $v3)) {
            $report->error(self::FILE, "{$where} ex3 must use the past participle \"{$v3}\"");
        }

        $ipa = $verb['ipa'] ?? null;

        if (Value::filled($ipa) && preg_match('/^\/.+\/\z/u', Value::trim($ipa)) !== 1) {
            $report->error(self::FILE, "{$where} ipa must look like /…/ – /…/ – /…/");
        }

        $romanian = implode(' ', array_map(fn ($key) => Value::show($verb[$key] ?? null), ['ro', 'ex2Ro', 'ex3Ro']));

        if (preg_match('/[şţŞŢ]/u', $romanian) === 1) {
            $report->error(self::FILE, "{$where} uses ş/ţ with cedilla — use ș/ț");
        }
    }

    /** Whether the sentence contains the form as a whole word ("lit / lighted": either one). */
    private function uses(string $sentence, string $form): bool
    {
        $alternatives = array_filter(array_map(fn ($part) => Value::trim($part), explode('/', $form)), fn ($part) => $part !== '');

        return array_any(
            $alternatives,
            fn ($alternative) => preg_match('/\b'.preg_quote($alternative, '/').'\b/iu', $sentence) === 1,
        );
    }
}
