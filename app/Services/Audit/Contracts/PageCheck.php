<?php

declare(strict_types=1);

namespace App\Services\Audit\Contracts;

use App\Enums\CheckGroup;
use App\Services\Audit\DTO\Finding;
use App\Services\Audit\DTO\PageContext;

interface PageCheck
{
    public function group(): CheckGroup;

    /** @return array<int, Finding> */
    public function run(PageContext $context): array;

    /**
     * Числовые и текстовые показатели страницы — попадают в metrics результата
     * независимо от того, есть находки или нет. Именно по ним считаются диффы.
     *
     * @return array<string, mixed>
     */
    public function metrics(PageContext $context): array;
}
