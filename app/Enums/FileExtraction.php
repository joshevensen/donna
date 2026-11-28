<?php

namespace App\Enums;

enum FileExtraction: string
{
    case Ready = 'ready';
    case Failed = 'failed';
}
