<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckTool: string
{
    case Blacklist = 'blacklist';
    case Provider = 'provider';
}

