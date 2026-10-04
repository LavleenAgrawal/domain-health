<?php

declare(strict_types=1);

namespace App\Services;

class BlacklistService
{
    public function __construct(private readonly SystemDnsResolver $resolver) {}

    /** @param array<string,mixed> $records */
    public function check(array $records, string $domain): array
    {
        $sources = [];
        $configured = 0;
        $checked = 0;
        $listed = false;
        $unavailable = false;

        foreach ((array) config('domain_health.blacklists', []) as $name => $source) {
            if (! ($source['enabled'] ?? false)) {
                $sources[] = ['source' => $name, 'status' => 'NOT_CONFIGURED', 'target' => null];

                continue;
            }

            $configured++;
            $targets = ($source['kind'] ?? null) === 'domain'
                ? [$domain]
                : $this->ipTargets($records['A']['values'] ?? []);

            if ($targets === []) {
                $sources[] = ['source' => $name, 'status' => 'NOT_APPLICABLE', 'target' => null];

                continue;
            }

            foreach ($targets as $target) {
                $checked++;
                $query = $this->queryName($target, (string) $source['zone'], (string) ($source['kind'] ?? 'ip'));
                $answer = $this->resolver->query($query, 'A');
                $answers = array_values(array_filter(array_map(
                    static fn (array $record): string => (string) ($record['data'] ?? ''),
                    $answer['records'] ?? [],
                )));

                if ($this->isAccessError((string) $name, $answers)) {
                    $status = 'ACCESS_ERROR';
                    $unavailable = true;
                } elseif (($answer['status'] ?? 'ERROR') === 'NXDOMAIN') {
                    $status = 'NOT_LISTED';
                } elseif (($answer['status'] ?? 'ERROR') === 'NOERROR' && $answers !== []) {
                    $status = 'LISTED';
                    $listed = true;
                } else {
                    $status = 'ERROR';
                    $unavailable = true;
                }

                $sources[] = [
                    'source' => $name,
                    'status' => $status,
                    'target' => $target,
                    'query' => $query,
                    'answers' => $answers,
                    'error' => $answer['error'] ?? null,
                ];
            }
        }

        $status = match (true) {
            $configured === 0 => 'NOT_CONFIGURED',
            $listed => 'LISTED',
            $unavailable => 'PARTIAL_UNKNOWN',
            default => 'CLEAN',
        };

        return [
            'status' => $status,
            'coverage' => $configured === 0 ? 'none' : ($unavailable ? 'incomplete' : 'complete'),
            'configured_sources' => $configured,
            'checked_queries' => $checked,
            'sources' => $sources,
        ];
    }

    /** @param array<int,mixed> $values
     * @return array<int,string>
     */
    private function ipTargets(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $value : '',
            $values,
        ))));
    }

    private function queryName(string $target, string $zone, string $kind): string
    {
        $lookup = $kind === 'ip' ? implode('.', array_reverse(explode('.', $target))) : rtrim(strtolower($target), '.');

        return $lookup.'.'.rtrim($zone, '.');
    }

    /** @param array<int,string> $answers */
    private function isAccessError(string $source, array $answers): bool
    {
        return match ($source) {
            'spamhaus_zen' => (bool) array_filter($answers, static fn (string $answer): bool => str_starts_with($answer, '127.255.255.')),
            'surbl' => in_array('127.0.0.1', $answers, true),
            default => false,
        };
    }
}
