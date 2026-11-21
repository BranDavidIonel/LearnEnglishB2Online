<?php

namespace App\Services\Content\Validation;

/** The kinds of course content, each with its own validator. */
enum ContentKind: string
{
    case Lessons = 'lessons';
    case Speaking = 'speaking';
    case Cambridge = 'cambridge';
    case Verbs = 'verbs';
    case Glossary = 'glossary';
    case Words = 'words';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function validator(string $root): Validator
    {
        return match ($this) {
            self::Lessons => new LessonValidator($root),
            self::Speaking => new SpeakingValidator($root),
            self::Cambridge => new CambridgeValidator($root),
            self::Verbs => new VerbValidator($root),
            self::Glossary => new GlossaryValidator($root),
            self::Words => new LessonWordsValidator($root),
        };
    }
}
