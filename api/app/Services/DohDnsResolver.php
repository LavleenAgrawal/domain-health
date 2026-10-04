<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** DNS-over-HTTPS gives explicit resolver and HTTP timeout control; it deliberately never issues ANY queries. */
class DohDnsResolver implements DnsResolver
{
    /**
     * @param  array<string,array{name:string,type:string}>  $queries
     * @return array<string,array{status:string,records:array<int,array<string,mixed>>,error:?string}>
     */
    public function queryMany(array $queries): array
    {
        $results = [];
        $pending = [];

        foreach ($queries as $key => $query) {
            $cacheKey = $this->cacheKey($query['name'], $query['type']);
            if ($cached = Cache::get($cacheKey)) {
                $results[$key] = $cached;
            } else {
                $pending[$key] = $query;
            }
        }

        if ($pending !== []) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($pending): array {
                    $requests = [];
                    foreach ($pending as $key => $query) {
                        $requests[$key] = $pool->as($key)
                            ->withHeaders(['Accept' => 'application/dns-json'])
                            ->withOptions($this->httpOptions())
                            ->timeout((int) config('domain_health.dns.timeout_seconds'))
                            ->get(config('domain_health.dns.resolver_url'), ['name' => $query['name'], 'type' => $query['type']]);
                    }

                    return $requests;
                });

                foreach ($pending as $key => $query) {
                    $response = $responses[$key] ?? null;
                    $results[$key] = $response instanceof Response
                        ? $this->responseResult($response, $query['name'], $query['type'])
                        : ['status' => 'TIMEOUT', 'records' => [], 'error' => $response ? class_basename($response) : 'ConnectionException'];
                }
            } catch (Throwable $exception) {
                foreach ($pending as $key => $query) {
                    $results[$key] = ['status' => 'TIMEOUT', 'records' => [], 'error' => class_basename($exception)];
                }
            }
        }

        return array_replace(array_fill_keys(array_keys($queries), []), $results);
    }

    public function query(string $name, string $type): array
    {
        $key = $this->cacheKey($name, $type);
        if ($cached = Cache::get($key)) {
            return $cached;
        }
        try {
            $response = Http::withHeaders(['Accept' => 'application/dns-json'])->withOptions($this->httpOptions())->timeout((int) config('domain_health.dns.timeout_seconds'))->retry(1, 100, null, false)->get(config('domain_health.dns.resolver_url'), ['name' => $name, 'type' => $type]);

            return $this->responseResult($response, $name, $type);
        } catch (Throwable $exception) {
            return ['status' => 'TIMEOUT', 'records' => [], 'error' => class_basename($exception)];
        }
    }

    private function responseResult(Response $response, string $name, string $type): array
    {
        if (! $response->successful()) {
            return ['status' => 'ERROR', 'records' => [], 'error' => 'Resolver HTTP '.$response->status()];
        }
        $body = $response->json();
        $status = match ((int) ($body['Status'] ?? 2)) {
            0 => 'NOERROR', 3 => 'NXDOMAIN', 2 => 'SERVFAIL', 5 => 'REFUSED', default => 'ERROR'
        };
        $result = ['status' => $status, 'records' => array_values($body['Answer'] ?? []), 'error' => $status === 'NOERROR' ? null : $status];
        if (in_array($status, ['NOERROR', 'NXDOMAIN'], true)) {
            Cache::put($this->cacheKey($name, $type), $result, now()->addSeconds($this->ttl($body, $status)));
        }

        return $result;
    }

    private function httpOptions(): array
    {
        $options = ['proxy' => config('domain_health.dns.http_proxy')];
        if ($caBundle = config('domain_health.dns.ca_bundle')) {
            $options['verify'] = $caBundle;
        }

        return $options;
    }

    private function cacheKey(string $name, string $type): string
    {
        return 'dns:'.hash('sha256', strtolower($name).'|'.$type);
    }

    private function ttl(array $body, string $status): int
    {
        $ttls = array_column($body['Answer'] ?? [], 'TTL');
        $ttl = $ttls === [] ? ($status === 'NXDOMAIN' ? 60 : (int) config('domain_health.dns.cache_seconds')) : min(array_map('intval', $ttls));

        return max(30, min($ttl, 3600));
    }
}
