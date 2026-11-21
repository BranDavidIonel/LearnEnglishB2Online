<?php

namespace App\Enums;

/**
 * How a word form relates to its headword ("alerts" is the plural of "alert", "went" the
 * past of "go"). "form" is a form the content lists without saying which one, or one whose
 * headword is both a noun and a verb ("works").
 */
enum WordRelation: string
{
    case Plural = 'plural';
    case Third = 'third';
    case Past = 'past';
    case Participle = 'participle';
    case Ing = 'ing';
    case Comparative = 'comparative';
    case Superlative = 'superlative';
    case Possessive = 'possessive';
    case Form = 'form';
}
