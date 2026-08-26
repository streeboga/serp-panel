<?php

declare(strict_types=1);

namespace SerpAudit\Contracts;

use SerpAudit\Finding;
use SerpAudit\PageContext;

interface PageCheck
{
    /** Устойчивый код вида `meta.title.missing` — по нему проверку включают и фильтруют находки. */
    public function code(): string;

    /** Категория из Category или своя строка. */
    public function category(): string;

    /** Название для каталога и интерфейса. */
    public function title(): string;

    /** @return array<int, Finding> */
    public function run(PageContext $context): array;

    /**
     * Показатели страницы — попадают в metrics результата независимо от того,
     * есть находки или нет. Именно по ним считаются диффы между прогонами.
     *
     * @return array<string, mixed>
     */
    public function metrics(PageContext $context): array;
}
