<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\CheckTool;
use App\Enums\ProcessingStatus;
use App\Models\CheckResult;
use App\Services\CheckResultCsvFormatter;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CheckResultCsvFormatterTest extends TestCase
{
    public function test_it_exports_the_complete_readable_report(): void
    {
        $attributes = [
            'original_input' => 'admin@example.com',
            'normalized_domain' => 'example.com',
            'status' => ProcessingStatus::Completed,
            'dns_records' => [
                'A' => ['status' => 'NOERROR', 'values' => ['192.0.2.10']],
                'MX' => ['status' => 'NOERROR', 'values' => [['priority' => 10, 'host' => 'mail.example.com']]],
                'SPF' => ['status' => 'NOERROR', 'values' => ['v=spf1 -all'], 'issue' => null],
            ],
            'findings' => ['blacklist' => ['status' => 'CLEAN', 'coverage' => 'complete', 'sources' => [['source' => 'surbl', 'status' => 'NOT_LISTED', 'target' => 'example.com', 'answers' => []]]]],
            'errors' => [],
            'checked_at' => new CarbonImmutable('2026-10-04 12:00:00'),
        ];
        $result = new class($attributes) extends CheckResult
        {
            public function __construct(private readonly array $values) {}

            public function getAttribute($key): mixed
            {
                return $this->values[$key] ?? null;
            }
        };
        $formatter = new CheckResultCsvFormatter;
        $row = array_combine($formatter->headers(), $formatter->row($result, CheckTool::Blacklist));

        self::assertSame('Clean', $row['Overall result']);
        self::assertSame('192.0.2.10', $row['IPv4 addresses']);
        self::assertSame('mail.example.com (priority 10)', $row['Mail servers']);
        self::assertStringContainsString('SURBL: Not listed', $row['Blocklist source details']);
        self::assertSame('Not requested', $row['DKIM']);
        self::assertSame('None', $row['Issues']);
    }
}
