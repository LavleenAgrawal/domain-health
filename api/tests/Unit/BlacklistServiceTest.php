<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BlacklistService;
use App\Services\SystemDnsResolver;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class BlacklistServiceTest extends TestCase
{
    public function test_disabled_sources_do_not_make_coverage_incomplete(): void
    {
        $this->configure([
            'surbl' => ['enabled' => true, 'kind' => 'domain', 'zone' => 'multi.surbl.org'],
            'spamrats' => ['enabled' => false, 'kind' => 'ip', 'zone' => 'all.spamrats.com'],
        ]);

        $result = (new BlacklistService($this->resolver([
            'example.com.multi.surbl.org' => ['status' => 'NXDOMAIN', 'records' => [], 'error' => null],
        ])))->check(['A' => ['values' => ['192.0.2.1']]], 'example.com');

        self::assertSame('CLEAN', $result['status']);
        self::assertSame('complete', $result['coverage']);
        self::assertSame('NOT_CONFIGURED', $result['sources'][1]['status']);
    }

    public function test_provider_access_responses_are_not_reported_as_listings(): void
    {
        $this->configure([
            'spamhaus_zen' => ['enabled' => true, 'kind' => 'ip', 'zone' => 'zen.spamhaus.org'],
        ]);

        $result = (new BlacklistService($this->resolver([
            '2.0.0.127.zen.spamhaus.org' => ['status' => 'NOERROR', 'records' => [['data' => '127.255.255.254']], 'error' => null],
        ])))->check(['A' => ['values' => ['127.0.0.2']]], 'example.com');

        self::assertSame('PARTIAL_UNKNOWN', $result['status']);
        self::assertSame('ACCESS_ERROR', $result['sources'][0]['status']);
    }

    /** @param array<string,array<string,mixed>> $sources */
    private function configure(array $sources): void
    {
        $container = new Container;
        $container->instance('config', new Repository(['domain_health.blacklists' => $sources]));
        Container::setInstance($container);
    }

    /** @param array<string,array<string,mixed>> $answers */
    private function resolver(array $answers): SystemDnsResolver
    {
        return new class($answers) extends SystemDnsResolver
        {
            /** @param array<string,array<string,mixed>> $answers */
            public function __construct(private readonly array $answers) {}

            public function query(string $name, string $type): array
            {
                return $this->answers[$name] ?? ['status' => 'NXDOMAIN', 'records' => [], 'error' => null];
            }
        };
    }
}
