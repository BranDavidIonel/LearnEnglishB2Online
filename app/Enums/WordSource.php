<?php

namespace App\Enums;

/**
 * Where the meaning of a tapped word comes from: the course vocabulary (unit words, word
 * banks, irregular verbs), the glossary of the other lesson words, or the glossary's names.
 */
enum WordSource: string
{
    case Course = 'course';
    case Glossary = 'glossary';
    case Name = 'name';
}
