<?php

namespace App\Enums;

enum InterviewAreaStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Covered = 'covered';
}
