<?php

declare(strict_types=1);

namespace App\Services;

interface DnsResolver
{
    /** @return array{status:string,records:array<int,array<string,mixed>>,error:?string} */
    public function query(string $name, string $type): array;
}

