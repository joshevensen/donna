<?php

namespace App\Enums;

enum CostPurpose: string
{
    case Reply = 'reply';
    case Tagging = 'tagging';
    case Extraction = 'extraction';
    case Embedding = 'embedding';
}
