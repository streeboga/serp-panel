<?php

declare(strict_types=1);

namespace App\Enums;

enum ScrapeJobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Retrying = 'retrying';
}
