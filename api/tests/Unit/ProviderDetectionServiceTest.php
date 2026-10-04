<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProviderDetectionService;
use PHPUnit\Framework\TestCase;

class ProviderDetectionServiceTest extends TestCase
{
    public function test_it_recognizes_google_and_microsoft_only_at_hostname_boundaries(): void
    {
        $service = new ProviderDetectionService;
        self::assertSame('Google Workspace', $service->detect(['status' => 'NOERROR', 'values' => [['priority' => 10, 'host' => 'alt1.aspmx.l.google.com']]])['provider']);
        self::assertSame('Google Workspace', $service->detect(['status' => 'NOERROR', 'values' => [['priority' => 5, 'host' => 'gmail-smtp-in.l.google.com']]])['provider']);
        self::assertSame('Microsoft 365', $service->detect(['status' => 'NOERROR', 'values' => [['priority' => 0, 'host' => 'contoso-com.mail.protection.outlook.com']]])['provider']);
        self::assertSame('Other', $service->detect(['status' => 'NOERROR', 'values' => [['priority' => 10, 'host' => 'mail.protection.outlook.com.evil.test']]])['provider']);
    }

    public function test_it_keeps_dns_failures_distinct_from_negative_detection(): void
    {
        self::assertSame('unknown', (new ProviderDetectionService)->detect(['status' => 'SERVFAIL', 'values' => []])['status']);
    }
}
