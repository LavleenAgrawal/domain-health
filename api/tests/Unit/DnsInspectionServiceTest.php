<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DnsInspectionService;
use App\Services\DnsResolver;
use PHPUnit\Framework\TestCase;

class DnsInspectionServiceTest extends TestCase
{
    public function testItReconstructsTxtChunksAndFlagsMultipleSpf(): void
    {
        $resolver = new class implements DnsResolver { public function query(string $name, string $type): array { return $type === 'TXT' ? ['status' => 'NOERROR', 'records' => [['data' => '"v=spf1 include:a" " include:b"'], ['data' => '"v=spf1 -all"']], 'error' => null] : ['status' => 'NXDOMAIN', 'records' => [], 'error' => null]; } };
        $result = (new DnsInspectionService($resolver))->inspect('example.com', null);
        self::assertSame('v=spf1 include:a include:b', $result['records']['SPF']['values'][0]); self::assertSame('MULTIPLE_RECORDS', $result['records']['SPF']['issue']); self::assertSame('NOT_CHECKED', $result['records']['DKIM']['status']);
    }
}
