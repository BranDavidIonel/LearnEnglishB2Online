<?php

namespace App\Enums;

/** The verdict on a Cambridge exam, from the latest mock results. */
enum Readiness: string
{
    // Every paper done, Reading and Listening and the average at the target or above.
    case Ready = 'ready';
    // An average of 60% or more.
    case Close = 'close';
    case NotYet = 'notyet';
    // No Reading or Listening result yet.
    case Unknown = 'unknown';
}
