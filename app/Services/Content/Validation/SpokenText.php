<?php

namespace App\Services\Content\Validation;

/**
 * The normalisation the app applies before it compares what the learner said with the model
 * answers (lower case, no punctuation, contractions written out): two answers that normalise
 * to the same text are the same answer, and a keyword must survive it.
 */
final class SpokenText
{
    /** Pattern => replacement, applied in this order. */
    private const array CONTRACTIONS = [
        "/\\bcan't\\b/" => 'can not',
        '/\bcannot\b/' => 'can not',
        "/\\bwon't\\b/" => 'will not',
        "/\\bshan't\\b/" => 'shall not',
        "/n't\\b/" => ' not',
        "/\\bi'm\\b/" => 'i am',
        "/'re\\b/" => ' are',
        "/'ve\\b/" => ' have',
        "/'ll\\b/" => ' will',
        "/\\b(he|she|it|that|there|what|where|who|how|here)'s\\b/" => '$1 is',
        "/\\blet's\\b/" => 'let us',
    ];

    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/[’‘`´]/u', "'", $text);
        $text = (string) preg_replace('/[“”"]/u', '', $text);
        $text = (string) preg_replace('/[.,!?;:()…]/u', ' ', $text);
        $text = (string) preg_replace('/'.Value::SPACE.'+-'.Value::SPACE.'+|—|–/u', ' ', $text);
        $text = self::collapse($text);

        foreach (self::CONTRACTIONS as $pattern => $replacement) {
            $text = (string) preg_replace($pattern.'u', $replacement, $text);
        }

        return self::collapse($text);
    }

    private static function collapse(string $text): string
    {
        return Value::trim((string) preg_replace('/'.Value::SPACE.'+/u', ' ', $text));
    }
}
