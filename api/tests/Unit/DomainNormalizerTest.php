<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DomainNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DomainNormalizerTest extends TestCase
{
    #[DataProvider('validValues')]
    public function test_it_extracts_and_normalizes_domains(string $input, string $expected): void
    {
        self::assertSame($expected, (new DomainNormalizer)->normalize($input));
    }

    public static function validValues(): array
    {
        return [[' Admin@EXAMPLE.COM. ', 'example.com'], ['www.Example.com.', 'www.example.com'], ['bücher.example', 'xn--bcher-kva.example']];
    }

    #[DataProvider('invalidValues')]
    public function test_it_rejects_urls_ips_and_malformed_input(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DomainNormalizer)->normalize($input);
    }

    public static function invalidValues(): array
    {
        return [['https://example.com'], ['192.0.2.1'], ['bad domain'], ['not-an-email@']];
    }

    public function test_it_accepts_missing_or_blank_selectors(): void
    {
        $normalizer = new DomainNormalizer;
        self::assertNull($normalizer->selector(null));
        self::assertNull($normalizer->selector('   '));
    }
}
