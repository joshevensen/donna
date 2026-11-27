<?php

namespace App\Enums;

enum MessageSource: string
{
    case Terminal = 'terminal';
    case Web = 'web';
}
