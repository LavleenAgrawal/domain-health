<?php

declare(strict_types=1);

namespace App\Services;

use Generator;
use RuntimeException;
use SplFileObject;

class UploadParser
{
    private const DOMAIN_HEADERS = ['domain', 'email', 'host', 'hostname'];

    public function __construct(private readonly DomainNormalizer $normalizer = new DomainNormalizer) {}

    /** @return array{rows:array<int,array{input:string,valid:bool,message:?string}>,total_values:int,valid_values:int,invalid_values:int} */
    public function preview(string $path): array
    {
        $rows = [];
        $totalValues = 0;
        $validValues = 0;
        $invalidValues = 0;

        foreach ($this->entries($path) as $entry) {
            $totalValues++;
            $valid = true;
            $message = null;
            try {
                $this->normalizer->normalize($entry['input']);
                $this->normalizer->selector($entry['selector']);
                $validValues++;
            } catch (\InvalidArgumentException $exception) {
                $valid = false;
                $message = $exception->getMessage();
                $invalidValues++;
            }
            if (count($rows) < 20) {
                $rows[] = ['input' => $entry['input'], 'valid' => $valid, 'message' => $message];
            }
        }

        return ['rows' => $rows, 'total_values' => $totalValues, 'valid_values' => $validValues, 'invalid_values' => $invalidValues];
    }

    /** @return Generator<int,array{input:string,selector:?string,row:int}> */
    public function entries(string $path): Generator
    {
        $rows = $this->lines($path);
        $firstRow = $rows->current() ?? [];
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $firstRow);
        $domainIndexes = [];

        foreach ($headers as $index => $header) {
            if (in_array($header, self::DOMAIN_HEADERS, true)) {
                $domainIndexes[] = $index;
            }
        }

        $hasHeader = $domainIndexes !== [];
        $selectorIndex = $hasHeader ? array_search('dkim_selector', $headers, true) : false;
        $entryCount = 0;
        $maxEntries = $this->maxEntries();

        foreach ($rows as $line => $row) {
            if ($line === 0 && $hasHeader) {
                continue;
            }

            $indexes = $hasHeader ? $domainIndexes : array_keys($row);
            $selector = $selectorIndex === false ? null : trim((string) ($row[$selectorIndex] ?? ''));

            foreach ($indexes as $index) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value === '') {
                    continue;
                }

                if (++$entryCount > $maxEntries) {
                    throw new RuntimeException('The upload exceeds the configured entry limit.');
                }

                yield ['input' => $value, 'selector' => $selector ?: null, 'row' => $line + 1];
            }
        }
    }

    /** @return Generator<int,array<int,string|null>> */
    private function lines(string $path): Generator
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        foreach ($file as $line => $row) {
            if (is_array($row) && $row !== [null]) {
                yield $line => $row;
            }
        }
    }

    private function maxEntries(): int
    {
        $container = app();

        return $container?->bound('config')
            ? (int) config('domain_health.uploads.max_rows', 10000)
            : 10000;
    }
}
