<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcessingStatus: string
{
    case Queued = 'queued';
    case Checking = 'checking';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool { return $this === self::Completed || $this === self::Failed; }
}

