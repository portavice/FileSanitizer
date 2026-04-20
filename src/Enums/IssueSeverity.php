<?php

namespace Portavice\FileSanitizer\Enums;

enum IssueSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
