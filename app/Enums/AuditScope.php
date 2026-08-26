<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditScope: string
{
    /** Весь сайт: sitemap + страницы из индекса + страницы проекта. */
    case Site = 'site';

    /** Только выбранные страницы проекта. */
    case Pages = 'pages';

    /** Один произвольный URL. */
    case Url = 'url';
}
