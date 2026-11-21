<?php

namespace App\Enums;

/** Where a best result comes from: the word games or the speaking games. */
enum ScoreArea: string
{
    case Games = 'games';
    case Speaking = 'speaking';
}
