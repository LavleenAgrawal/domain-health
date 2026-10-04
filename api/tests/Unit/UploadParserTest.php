<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\UploadParser;
use PHPUnit\Framework\TestCase;

class UploadParserTest extends TestCase
{
    /** @var array<int,string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function test_headerless_text_treats_every_line_as_data(): void
    {
        $entries = iterator_to_array((new UploadParser)->entries($this->file("gmail.com\nadmin@outlook.com\nexample.com\n")));

        self::assertSame(['gmail.com', 'admin@outlook.com', 'example.com'], array_column($entries, 'input'));
        self::assertSame([1, 2, 3], array_column($entries, 'row'));
    }

    public function test_headerless_csv_uses_every_non_empty_cell(): void
    {
        $entries = iterator_to_array((new UploadParser)->entries($this->file("example.com,admin@example.net\ngoogle.com,\n")));

        self::assertSame(['example.com', 'admin@example.net', 'google.com'], array_column($entries, 'input'));
    }

    public function test_structured_csv_uses_every_domain_column_and_keeps_selector_metadata(): void
    {
        $entries = iterator_to_array((new UploadParser)->entries($this->file("domain,email,dkim_selector,note\nexample.com,admin@example.net,selector1,Acme\ngoogle.com,,,\n")));

        self::assertSame(['example.com', 'admin@example.net', 'google.com'], array_column($entries, 'input'));
        self::assertSame(['selector1', 'selector1', null], array_column($entries, 'selector'));
    }

    public function test_preview_reports_all_automatically_detected_values(): void
    {
        $preview = (new UploadParser)->preview($this->file("gmail.com\noutlook.com\nexample.com\n"));

        self::assertSame(3, $preview['total_values']);
        self::assertSame(3, $preview['valid_values']);
        self::assertSame(0, $preview['invalid_values']);
        self::assertSame(['gmail.com', 'outlook.com', 'example.com'], array_column($preview['rows'], 'input'));
    }

    public function test_preview_identifies_invalid_values_before_submission(): void
    {
        $preview = (new UploadParser)->preview($this->file("gmail.com\nnot a domain\nhttps://example.com\n"));

        self::assertSame(3, $preview['total_values']);
        self::assertSame(1, $preview['valid_values']);
        self::assertSame(2, $preview['invalid_values']);
        self::assertFalse($preview['rows'][1]['valid']);
        self::assertNotEmpty($preview['rows'][1]['message']);
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'domains-');
        self::assertNotFalse($path);
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }
}
