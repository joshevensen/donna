<?php

namespace App\Enums;

enum FactProvenance: string
{
    case Remember = 'remember';
    case Extraction = 'extraction';
    case Interview = 'interview';
    case Correction = 'correction';
}
