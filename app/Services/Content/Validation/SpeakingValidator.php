<?php

namespace App\Services\Content\Validation;

/**
 * The speaking practice of every level (docs/content/SCHEMA-SPEAKING.md): role-play scenarios,
 * phrase topics and open questions. Everything here is said aloud, so long answers and digits
 * (which speech recognition writes in many ways) are flagged.
 */
final class SpeakingValidator extends Validator
{
    private const int SCENARIOS = 8;

    private const int PHRASE_TOPICS = 6;

    private const int PHRASES_PER_TOPIC = 10;

    private const int QUESTIONS = 12;

    /** Expected length of a model answer to an open question, in words. */
    private const array SAMPLE_WORDS = ['A1' => [6, 24], 'A2' => [12, 34], 'B1' => [20, 50], 'B2' => [35, 80]];

    public function validate(ValidationReport $report): void
    {
        foreach (LessonValidator::LEVELS as $level) {
            $this->level($report, $level);
        }
    }

    private function level(ValidationReport $report, string $level): void
    {
        $file = "{$level}/speaking.json";

        if (! $this->exists($file)) {
            $report->error($file, 'file missing');

            return;
        }

        $speaking = $this->decode($file, $report);

        if ($speaking === null) {
            return;
        }

        $upper = strtoupper($level);

        if (($speaking['level'] ?? null) !== $upper) {
            $report->error($file, "level must be \"{$upper}\"");
        }

        $this->scenarios($report, $file, $level, $speaking['scenarios'] ?? null);
        $this->phrases($report, $file, $speaking['phrases'] ?? null);
        $this->questions($report, $file, $upper, $speaking['questions'] ?? null);
    }

    private function scenarios(ValidationReport $report, string $file, string $level, mixed $scenarios): void
    {
        $count = Value::countLabel($scenarios);

        if (! Value::isList($scenarios) || count($scenarios) !== self::SCENARIOS) {
            $report->error($file, 'needs exactly '.self::SCENARIOS." scenarios (has {$count})");
        }

        foreach (Value::items($scenarios) as $i => $scenario) {
            $where = "scenarios[{$i}]";
            $scenario = Value::fields($scenario);
            $id = "{$level}-s".str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);

            if (($scenario['id'] ?? null) !== $id) {
                $report->error($file, "{$where}: id must be \"{$id}\"");
            }

            foreach (['emoji', 'title', 'titleRo', 'youRo', 'partner'] as $key) {
                if (! Value::filled($scenario[$key] ?? null)) {
                    $report->error($file, "{$where}: missing {$key}");
                }
            }

            if (array_key_exists('closing', $scenario) && ! Value::filled($scenario['closing'])) {
                $report->error($file, "{$where}: empty closing");
            }

            $turns = $scenario['turns'] ?? null;
            $turnCount = Value::countLabel($turns);

            if (! Value::isList($turns) || count($turns) < 6 || count($turns) > 8) {
                $report->error($file, "{$where}: needs 6-8 turns (has {$turnCount})");

                continue;
            }

            foreach ($turns as $t => $turn) {
                $this->turn($report, $file, Value::fields($turn), "{$where}.turns[{$t}]");
            }
        }
    }

    /** @param  array<array-key, mixed>  $turn */
    private function turn(ValidationReport $report, string $file, array $turn, string $where): void
    {
        foreach (['say', 'sayRo', 'hintRo'] as $key) {
            if (! Value::filled($turn[$key] ?? null)) {
                $report->error($file, "{$where}: missing {$key}");
            }
        }

        $answers = $turn['answers'] ?? null;

        if (! Value::isList($answers) || count($answers) < 2 || count($answers) > 4 || ! array_all($answers, fn ($answer) => Value::filled($answer))) {
            $report->error($file, "{$where}: needs 2-4 answers");

            return;
        }

        $answers = array_map(fn ($answer) => Value::text($answer), $answers);

        foreach ($answers as $answer) {
            $words = Value::wordCount(Value::trim($answer));

            if ($words > 16) {
                $report->warning($file, "{$where}: answer has {$words} words: \"{$answer}\"");
            }

            if (preg_match('/\d/', $answer) === 1) {
                $report->warning($file, "{$where}: digits in a spoken answer: \"{$answer}\"");
            }
        }

        $normalized = array_map(fn ($answer) => SpokenText::normalize($answer), $answers);

        if (count(array_unique($normalized)) !== count($answers)) {
            $report->error($file, "{$where}: duplicate answers");
        }

        $keywords = $turn['keywords'] ?? null;

        if (! Value::isList($keywords) || count($keywords) < 1 || count($keywords) > 3 || ! array_all($keywords, fn ($keyword) => Value::filled($keyword))) {
            $report->error($file, "{$where}: needs 1-3 keywords");

            return;
        }

        foreach (array_map(fn ($keyword) => Value::text($keyword), $keywords) as $keyword) {
            if ($keyword !== mb_strtolower($keyword)) {
                $report->error($file, "{$where}: keyword must be lowercase: \"{$keyword}\"");
            }

            // The app accepts a spoken answer only when every keyword is in it.
            $needle = ' '.SpokenText::normalize($keyword).' ';

            foreach ($answers as $i => $answer) {
                if (! str_contains(' '.$normalized[$i].' ', $needle)) {
                    $report->error($file, "{$where}: keyword \"{$keyword}\" is missing from answer \"{$answer}\"");
                }
            }
        }
    }

    private function phrases(ValidationReport $report, string $file, mixed $topics): void
    {
        $count = Value::countLabel($topics);

        if (! Value::isList($topics) || count($topics) !== self::PHRASE_TOPICS) {
            $report->error($file, 'needs exactly '.self::PHRASE_TOPICS." phrase topics (has {$count})");
        }

        // A phrase may appear only once in the whole file.
        $seen = [];

        foreach (Value::items($topics) as $i => $topic) {
            $where = "phrases[{$i}]";
            $topic = Value::fields($topic);

            foreach (['topic', 'topicRo', 'emoji'] as $key) {
                if (! Value::filled($topic[$key] ?? null)) {
                    $report->error($file, "{$where}: missing {$key}");
                }
            }

            $items = $topic['items'] ?? null;
            $itemCount = Value::countLabel($items);

            if (! Value::isList($items) || count($items) !== self::PHRASES_PER_TOPIC) {
                $report->error($file, "{$where}: needs exactly ".self::PHRASES_PER_TOPIC." items (has {$itemCount})");

                continue;
            }

            foreach ($items as $j => $item) {
                $this->phrase($report, $file, Value::fields($item), "{$where}.items[{$j}]", $seen);
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $item
     * @param  array<string, true>  $seen
     */
    private function phrase(ValidationReport $report, string $file, array $item, string $where, array &$seen): void
    {
        $en = $item['en'] ?? null;

        if (! Value::filled($en) || ! Value::filled($item['ro'] ?? null)) {
            $report->error($file, "{$where}: needs en + ro");

            return;
        }

        $words = Value::wordCount(Value::trim($en));

        if ($words < 2 || $words > 13) {
            $report->warning($file, "{$where}: {$words} words: \"{$en}\"");
        }

        if (preg_match('/\d/', $en) === 1) {
            $report->warning($file, "{$where}: digits in a spoken phrase: \"{$en}\"");
        }

        $key = SpokenText::normalize($en);

        if (isset($seen[$key])) {
            $report->error($file, "{$where}: phrase repeated: \"{$en}\"");
        } else {
            $seen[$key] = true;
        }
    }

    private function questions(ValidationReport $report, string $file, string $level, mixed $questions): void
    {
        $count = Value::countLabel($questions);

        if (! Value::isList($questions) || count($questions) !== self::QUESTIONS) {
            $report->error($file, 'needs exactly '.self::QUESTIONS." questions (has {$count})");
        }

        [$min, $max] = self::SAMPLE_WORDS[$level];

        foreach (Value::items($questions) as $i => $question) {
            $where = "questions[{$i}]";
            $question = Value::fields($question);

            foreach (['q', 'qRo', 'sample'] as $key) {
                if (! Value::filled($question[$key] ?? null)) {
                    $report->error($file, "{$where}: missing {$key}");
                }
            }

            $starters = $question['starters'] ?? null;

            if (! Value::isList($starters) || count($starters) < 2 || count($starters) > 3 || ! array_all($starters, fn ($starter) => Value::filled($starter))) {
                $report->error($file, "{$where}: needs 2-3 starters");
            }

            $sample = $question['sample'] ?? null;

            if (Value::filled($sample)) {
                $words = Value::wordCount(Value::trim($sample));

                if ($words < $min || $words > $max) {
                    $report->warning($file, "{$where}: sample has {$words} words (expected {$min}-{$max})");
                }
            }
        }
    }
}
