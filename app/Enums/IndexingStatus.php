<?php

declare(strict_types=1);

namespace App\Enums;

enum IndexingStatus: string
{
    case Idle = 'idle';
    case Indexing = 'indexing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
