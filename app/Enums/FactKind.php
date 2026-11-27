<?php

namespace App\Enums;

enum FactKind: string
{
    case Fact = 'fact';
    case Preference = 'preference';
    case Decision = 'decision';
    case Opinion = 'opinion';
    case Moment = 'moment';
}
