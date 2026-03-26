<?php

declare(strict_types=1);

namespace App\Enums;

enum ClassifiedBy: string
{
    case Rule = 'rule';
    case Manual = 'manual';
}
