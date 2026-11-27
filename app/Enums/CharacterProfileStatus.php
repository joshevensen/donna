<?php

namespace App\Enums;

enum CharacterProfileStatus: string
{
    case Proposed = 'proposed';
    case Active = 'active';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
}
