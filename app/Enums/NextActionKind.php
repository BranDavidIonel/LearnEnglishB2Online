<?php

namespace App\Enums;

/** What the learner should do next (the big button on the home page). */
enum NextActionKind: string
{
    // A step (or the test) of a unit.
    case Unit = 'unit';
    // Every unit of the level is passed: the level exam.
    case Exam = 'exam';
    // The whole course is done.
    case Done = 'done';
}
