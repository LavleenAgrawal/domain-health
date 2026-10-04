<?php

declare(strict_types=1);

namespace App\Services;

class DnsInspectionService
{
    public function __construct(private readonly DnsResolver $resolver) {}

    /** @return array{records:array<string,mixed>,errors:array<int,array<string,string>>} */
    public function inspect(string $domain, ?string $selector, bool $showAll = false): array
    {
        $types = $showAll ? ['A', 'AAAA', 'MX', 'TXT', 'CNAME', 'NS', 'CAA', 'SRV'] : ['A', 'AAAA', 'MX', 'TXT', 'CNAME', 'NS'];
        $queries = [];
        foreach ($types as $type) {
            $queries[$type] = ['name' => $domain, 'type' => $type];
        }
        $queries['DMARC'] = ['name' => '_dmarc.'.$domain, 'type' => 'TXT'];
        if ($selector) {
            $queries['DKIM'] = ['name' => $selector.'._domainkey.'.$domain, 'type' => 'TXT'];
        }

        $answers = $this->queryMany($queries);
        $records = [];
        $errors = [];
        foreach ($types as $type) {
            [$records[$type], $error] = $this->recordsFromAnswer($answers[$type], $type);
            if ($error) {
                $errors[] = ['check' => $type, 'status' => $error];
            }
        }
        $records['SPF'] = $this->filteredTxt($records['TXT'], fn (string $value): bool => str_starts_with(strtolower($value), 'v=spf1'));
        [$dmarcRecords, $dmarcError] = $this->recordsFromAnswer($answers['DMARC'], 'TXT');
        $records['DMARC'] = $this->filteredTxt($dmarcRecords, fn (string $value): bool => str_starts_with(strtolower($value), 'v=dmarc1'));
        if ($dmarcError) {
            $errors[] = ['check' => 'DMARC', 'status' => $dmarcError];
        }
        if ($selector) {
            [$dkimRecords, $dkimError] = $this->recordsFromAnswer($answers['DKIM'], 'TXT');
            $records['DKIM'] = $this->filteredTxt($dkimRecords, fn (string $value): bool => str_starts_with(strtolower($value), 'v=dkim1'));
            if ($dkimError) {
                $errors[] = ['check' => 'DKIM', 'status' => $dkimError];
            }
        } else {
            $records['DKIM'] = ['status' => 'NOT_CHECKED', 'values' => []];
        }
        $records['PTR'] = $this->ptr($records['A']);

        return ['records' => $records, 'errors' => $errors];
    }

    private function recordsFromAnswer(array $answer, string $type): array
    {
        return [['status' => $answer['status'], 'values' => array_map(fn (array $record) => $this->format($record, $type), $answer['records'])], $answer['error']];
    }

    private function filteredTxt(array $item, callable $matches): array
    {
        $values = array_values(array_filter($item['values'], $matches));

        return ['status' => $item['status'], 'values' => $values, 'issue' => count($values) > 1 ? 'MULTIPLE_RECORDS' : null];
    }

    private function ptr(array $aRecords): array
    {
        $queries = [];
        foreach ($aRecords['values'] ?? [] as $index => $ip) {
            $queries[(string) $index] = ['name' => implode('.', array_reverse(explode('.', $ip))).'.in-addr.arpa', 'type' => 'PTR'];
        } $values = [];
        foreach ($this->queryMany($queries) as $answer) {
            foreach ($answer['records'] as $record) {
                $values[] = $this->format($record, 'PTR');
            }
        }

return ['status' => $values ? 'NOERROR' : 'NOT_APPLICABLE', 'values' => $values];
    }

    private function queryMany(array $queries): array
    {
        if (method_exists($this->resolver, 'queryMany')) {
            return $this->resolver->queryMany($queries);
        } $answers = [];
        foreach ($queries as $key => $query) {
            $answers[$key] = $this->resolver->query($query['name'], $query['type']);
        }

return $answers;
    }

    private function format(array $record, string $type): string|array
    {
        $value = trim((string) ($record['data'] ?? ''), '"');
        if ($type === 'MX') {
            [$priority, $host] = array_pad(explode(' ', $value, 2), 2, '');

            return ['priority' => (int) $priority, 'host' => rtrim($host, '.')];
        }

return preg_replace('/"\s*"/', '', $value) ?? $value;
    }
}
