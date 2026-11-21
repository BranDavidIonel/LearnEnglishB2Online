<?php

namespace App\Services\Content\Validation;

/** How bad a content problem is: errors stop a deploy, warnings only ask for a second look. */
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
