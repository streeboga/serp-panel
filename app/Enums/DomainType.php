<?php

declare(strict_types=1);

namespace App\Enums;

enum DomainType: string
{
    case Own = 'own';
    case Competitor = 'competitor';
    case Satellite = 'satellite';
}
