<?php

namespace App\Enums;

enum PersonKind: string
{
    case Self = 'self';
    case Donna = 'donna';
    case Other = 'other';
}
