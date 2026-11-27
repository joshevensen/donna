<?php

namespace App\Enums;

enum ExtractionRunStatus: string
{
    case Submitted = 'submitted';
    case Applied = 'applied';
    case Failed = 'failed';
}
