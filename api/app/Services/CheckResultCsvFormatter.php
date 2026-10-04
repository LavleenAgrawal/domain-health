<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CheckTool;
use App\Models\CheckResult;

class CheckResultCsvFormatter
{
    private const RECORD_LABELS = [
        'A' => 'IPv4 addresses',
        'AAAA' => 'IPv6 addresses',
        'MX' => 'Mail servers',
        'NS' => 'Nameservers',
        'CNAME' => 'Aliases',
        'TXT' => 'TXT records',
        'SPF' => 'SPF',
        'DMARC' => 'DMARC',
        'DKIM' => 'DKIM',
        'PTR' => 'Reverse DNS',
        'CAA' => 'Certificate authorities',
        'SRV' => 'Service records',
    ];

    public function headers(): array
    {
        return array_merge([
            'Domain',
            'Overall result',
            'What this means',
            'Blocklist coverage',
            'Blocklist source details',
            'Mail provider',
            'Provider explanation',
        ], array_values(self::RECORD_LABELS), ['Issues', 'Checked at']);
    }

    public function row(CheckResult $result, CheckTool $tool): array
    {
        $dns = $result->dns_records ?? [];
        $blacklist = $result->findings['blacklist'] ?? [];
        $provider = $result->findings['provider'] ?? [];
        $overall = $tool === CheckTool::Provider
            ? ($provider['provider'] ?? 'Not detected')
            : $this->statusLabel($blacklist['status'] ?? ($result->status->value === 'failed' ? 'FAILED' : 'UNKNOWN'));

        $row = [
            $result->normalized_domain ?: $result->original_input,
            $overall,
            $this->summary($result, $tool),
            $tool === CheckTool::Blacklist ? $this->statusLabel($blacklist['coverage'] ?? 'none') : 'Not applicable',
            $tool === CheckTool::Blacklist ? $this->sources($blacklist['sources'] ?? []) : 'Not applicable',
            $tool === CheckTool::Provider ? ($provider['provider'] ?? 'Not detected') : 'Not applicable',
            $tool === CheckTool::Provider ? ($provider['reason'] ?? 'No provider evidence available.') : 'Not applicable',
        ];

        foreach (array_keys(self::RECORD_LABELS) as $type) {
            $row[] = $this->record($dns[$type] ?? null);
        }

        $row[] = $this->errors($result->errors ?? []);
        $row[] = optional($result->checked_at)?->toIso8601String() ?? 'Not completed';

        return $row;
    }

    private function summary(CheckResult $result, CheckTool $tool): string
    {
        if ($result->status->value === 'failed') {
            return 'This value could not be checked. See Issues for the reason.';
        }
        if ($tool === CheckTool::Provider) {
            return $result->findings['provider']['reason'] ?? 'The public mail records did not identify a provider.';
        }

        return match ($result->findings['blacklist']['status'] ?? null) {
            'CLEAN' => 'Every configured blocklist check completed and none reported a listing.',
            'LISTED' => 'At least one configured blocklist reported a listing.',
            'PARTIAL_UNKNOWN' => 'At least one configured blocklist could not be checked.',
            'NOT_CONFIGURED' => 'No blocklist provider is configured.',
            default => 'No completed blocklist conclusion is available.',
        };
    }

    private function record(?array $record): string
    {
        if ($record === null) {
            return 'Not requested';
        }
        $values = array_map(fn (mixed $value): string => $this->value($value), $record['values'] ?? []);
        if ($values !== []) {
            $text = implode(' | ', $values);

            return ($record['issue'] ?? null) ? $text.' ('.$this->statusLabel($record['issue']).')' : $text;
        }

        return $this->statusLabel($record['status'] ?? 'UNKNOWN');
    }

    private function value(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value) && isset($value['host'])) {
            return (string) $value['host'].(isset($value['priority']) ? ' (priority '.(int) $value['priority'].')' : '');
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    private function sources(array $sources): string
    {
        if ($sources === []) {
            return 'No sources available';
        }

        return implode(' | ', array_map(function (array $source): string {
            $parts = [strtoupper(str_replace('_', ' ', (string) ($source['source'] ?? 'source'))).': '.$this->statusLabel($source['status'] ?? 'UNKNOWN')];
            if ($source['target'] ?? null) {
                $parts[] = 'target '.(string) $source['target'];
            }
            if ($source['answers'] ?? []) {
                $parts[] = 'response '.implode(', ', $source['answers']);
            }

            return implode('; ', $parts);
        }, $sources));
    }

    private function errors(array $errors): string
    {
        if ($errors === []) {
            return 'None';
        }

        return implode(' | ', array_map(fn (array $error): string => ($error['check'] ?? 'Check').': '.$this->statusLabel($error['status'] ?? 'Unknown'), $errors));
    }

    private function statusLabel(string $status): string
    {
        return ucfirst(strtolower(str_replace('_', ' ', $status)));
    }
}
