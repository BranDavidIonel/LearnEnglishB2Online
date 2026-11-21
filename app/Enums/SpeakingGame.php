<?php

namespace App\Enums;

/** The speaking games that keep a record (the higher, the better). */
enum SpeakingGame: string
{
    case Pairs = 'pairs';
    case Sprint = 'sprint';
}
