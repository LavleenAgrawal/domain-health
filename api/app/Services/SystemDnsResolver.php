<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/** Uses the machine's attributable resolver for DNSBLs that reject public DoH resolvers. */
class SystemDnsResolver implements DnsResolver
{
    public function query(string $name, string $type): array
    {
        if ($type !== 'A') {
            return ['status' => 'ERROR', 'records' => [], 'error' => 'Unsupported record type'];
        }

        $key = 'system-dns:'.hash('sha256', strtolower($name).'|'.$type);
        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $records = @dns_get_record($name, DNS_A);
        if ($records === false) {
            return ['status' => 'ERROR', 'records' => [], 'error' => 'System DNS query failed'];
        }

        $result = [
            'status' => $records === [] ? 'NXDOMAIN' : 'NOERROR',
            'records' => array_values(array_map(fn (array $record) => [
                'name' => $record['host'] ?? $name,
                'type' => 1,
                'TTL' => (int) ($record['ttl'] ?? 60),
                'data' => $record['ip'] ?? '',
            ], $records)),
            'error' => null,
        ];

        Cache::put($key, $result, now()->addSeconds($records === [] ? 60 : 300));

        return $result;
    }
}
