<?php

declare(strict_types=1);

namespace App\Enums;

enum Device: string
{
    case Desktop = 'desktop';
    case Mobile = 'mobile';
}
