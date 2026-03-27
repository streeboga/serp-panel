<?php

declare(strict_types=1);

namespace App\Enums;

enum PageType: string
{
    case Commercial = 'commercial';
    case Informational = 'informational';
    case Navigational = 'navigational';
    case Transactional = 'transactional';
}
