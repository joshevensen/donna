<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Proposed = 'proposed';
    case Active = 'active';
    case Parked = 'parked';
    case Dropped = 'dropped';
}
