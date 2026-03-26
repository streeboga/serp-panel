<?php

declare(strict_types=1);

namespace App\Enums;

enum ClassificationRuleType: string
{
    case DomainExact = 'domain_exact';
    case DomainContains = 'domain_contains';
    case DomainRegex = 'domain_regex';
    case UrlRegex = 'url_regex';
    case TitleContains = 'title_contains';
}
