<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckGroup: string
{
    case Technical = 'technical';
    case Meta = 'meta';
    case Content = 'content';
    case Links = 'links';
    case Images = 'images';
}
